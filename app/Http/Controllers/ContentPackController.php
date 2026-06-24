<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackChangeRequest;
use App\Models\ContentPackPhoto;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Jobs\NotifyFollowersJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContentPackController extends Controller
{
    private function priceLimits(): array
    {
        return [
            'min' => (int) PlatformSetting::get('content_pack_price_min', 100),
            'max' => (int) PlatformSetting::get('content_pack_price_max', 10000),
        ];
    }

    private function moderationSettings(): array
    {
        return [
            'new_packs'      => (bool) (int) PlatformSetting::get('moderate_new_packs', 1),
            'existing_packs' => (bool) (int) PlatformSetting::get('moderate_existing_packs', 0),
        ];
    }

    /**
     * Create or update a pending change request for a published pack.
     * Returns the data to include in the JSON response.
     */
    private function upsertChangeRequest(ContentPack $pack, string $field, mixed $value): array
    {
        DB::transaction(function () use ($pack, $field, $value, &$changeRequest) {
            $changeRequest = ContentPackChangeRequest::where('content_pack_id', $pack->id)
                ->whereIn('status', ['pending', 'has_remarks'])
                ->lockForUpdate()
                ->first();

            $currentValue = $pack->{$field};
            $isSameAsCurrent = match ($field) {
                'price'       => (int) $value === (int) $currentValue,
                'description' => (string) ($value ?? '') === (string) ($currentValue ?? ''),
                default       => (string) $value === (string) $currentValue,
            };

            if ($changeRequest) {
                $changedFields = $changeRequest->changed_fields ?? [];

                if ($isSameAsCurrent) {
                    // Remove this field from the pending request
                    $changedFields = array_values(array_filter($changedFields, fn ($f) => $f !== $field));
                    if (empty($changedFields)) {
                        $changeRequest->delete();
                        $changeRequest = null;
                        return;
                    }
                    // Also remove from flagged if it was flagged
                    $flaggedFields = array_values(array_filter($changeRequest->flagged_fields ?? [], fn ($f) => $f !== $field));
                    $fieldComments = array_filter($changeRequest->field_comments ?? [], fn ($k) => $k !== $field, ARRAY_FILTER_USE_KEY);
                    $changeRequest->update([
                        'changed_fields'   => $changedFields,
                        "pending_{$field}" => null,
                        'flagged_fields'   => $flaggedFields ?: null,
                        'field_comments'   => $fieldComments ?: null,
                        'status'           => 'pending',
                    ]);
                } else {
                    if (!in_array($field, $changedFields)) {
                        $changedFields[] = $field;
                    }
                    // Remove this field from flagged (user fixed it)
                    $flaggedFields = array_values(array_filter($changeRequest->flagged_fields ?? [], fn ($f) => $f !== $field));
                    $fieldComments = array_filter($changeRequest->field_comments ?? [], fn ($k) => $k !== $field, ARRAY_FILTER_USE_KEY);
                    // If no more flagged fields, reset status to pending
                    $newStatus = empty($flaggedFields) ? 'pending' : $changeRequest->status;
                    $changeRequest->update([
                        'changed_fields'   => $changedFields,
                        "pending_{$field}" => $value,
                        'flagged_fields'   => $flaggedFields ?: null,
                        'field_comments'   => $fieldComments ?: null,
                        'status'           => $newStatus,
                    ]);
                }
            } else {
                if ($isSameAsCurrent) {
                    return; // nothing to do
                }
                $changeRequest = ContentPackChangeRequest::create([
                    'content_pack_id'  => $pack->id,
                    'changed_fields'   => [$field],
                    "pending_{$field}" => $value,
                    'status'           => 'pending',
                ]);
            }
        });

        if (!isset($changeRequest) || $changeRequest === null) {
            // Pending request was removed (user reverted to original value)
            return [
                $field          => $value,
                'pending'       => false,
                'pending_change' => null,
            ];
        }

        return [
            $field          => $value,
            'pending'       => true,
            'pending_change' => [
                'changed_fields'      => $changeRequest->changed_fields,
                'pending_title'       => $changeRequest->pending_title,
                'pending_description' => $changeRequest->pending_description,
                'pending_price'       => $changeRequest->pending_price,
            ],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        abort_if(!$request->user()->is_idol, 403);

        $limits = $this->priceLimits();
        $data = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price'       => ['required', 'integer', 'min:' . $limits['min'], 'max:' . $limits['max']],
            'photos'      => ['required', 'array', 'min:1', 'max:50'],
            'photos.*'    => ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'cover_index' => ['required', 'integer', 'min:0'],
        ], [
            'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
            'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
        ]);

        $moderation = $this->moderationSettings();
        $status     = $moderation['new_packs'] ? 'pending_review' : 'approved';

        $pack = ContentPack::create([
            'user_id'      => $request->user()->id,
            'title'        => $data['title'],
            'description'  => $data['description'] ?? null,
            'price'        => $data['price'],
            'status'       => $status,
            'published_at' => null,
        ]);

        $coverIndex = $data['cover_index'] ?? null;
        $coverPath  = null;

        foreach ($request->file('photos', []) as $index => $file) {
            $path = $this->compressAndStorePhoto($file, $pack->id);
            ContentPackPhoto::create([
                'content_pack_id'   => $pack->id,
                'path'              => $path,
                'original_filename' => $file->getClientOriginalName(),
                'sort_order'        => $index,
            ]);
            if ((int)$index === (int)$coverIndex) {
                $coverPath = $path;
            }
        }

        if ($coverPath) {
            $pack->update(['cover_path' => $coverPath]);
        }

        return response()->json(['id' => $pack->id, 'status' => $pack->status]);
    }

    public function update(Request $request, ContentPack $pack): RedirectResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'has_remarks', 422);

        $review = $pack->latestReview;
        $flaggedFields = $review?->flagged_fields ?? [];
        $flaggedPhotoIds = $review?->flagged_photo_ids ?? [];

        $limits = $this->priceLimits();
        $rules = [];
        if (in_array('title', $flaggedFields)) {
            $rules['title'] = ['sometimes', 'string', 'max:120'];
        }
        if (in_array('description', $flaggedFields)) {
            $rules['description'] = ['sometimes', 'nullable', 'string', 'max:2000'];
        }
        if (in_array('price', $flaggedFields)) {
            $rules['price'] = ['sometimes', 'integer', 'min:' . $limits['min'], 'max:' . $limits['max']];
        }
        if (!empty($flaggedPhotoIds)) {
            $rules['photos']   = ['sometimes', 'array'];
            $rules['photos.*'] = ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'];
            $rules['replace_photo_ids']   = ['sometimes', 'array'];
            $rules['replace_photo_ids.*'] = ['integer'];
            $rules['delete_photo_ids']    = ['sometimes', 'array'];
            $rules['delete_photo_ids.*']  = ['integer'];
        }

        $data = $request->validate($rules, [
            'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
            'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
        ]);

        $updateFields = [];
        if (isset($data['title']))       $updateFields['title']       = $data['title'];
        if (array_key_exists('description', $data)) $updateFields['description'] = $data['description'];
        if (isset($data['price']))       $updateFields['price']       = $data['price'];

        if (!empty($updateFields)) {
            $pack->update($updateFields);
        }

        // Handle photo deletions
        $deleteIds = array_intersect(
            array_map('intval', $data['delete_photo_ids'] ?? []),
            $flaggedPhotoIds
        );
        foreach ($deleteIds as $photoId) {
            $photo = ContentPackPhoto::find($photoId);
            if ($photo && $photo->content_pack_id === $pack->id) {
                Storage::disk('local')->delete($photo->path);
                if ($pack->cover_path === $photo->path) {
                    $pack->update(['cover_path' => null]);
                }
                $photo->delete();
            }
        }

        // Handle photo replacements
        if ($request->hasFile('photos') && !empty($flaggedPhotoIds)) {
            $replaceIds = $data['replace_photo_ids'] ?? [];
            $files      = $request->file('photos');

            foreach ($files as $i => $file) {
                $photoId = $replaceIds[$i] ?? null;
                if (!$photoId || !in_array((int)$photoId, $flaggedPhotoIds)) {
                    continue;
                }
                $photo = ContentPackPhoto::find($photoId);
                if (!$photo || $photo->content_pack_id !== $pack->id) {
                    continue;
                }
                // Delete old file
                Storage::disk('local')->delete($photo->path);
                // Store new with compression
                $path = $this->compressAndStorePhoto($file, $pack->id);
                $photo->update(['path' => $path, 'original_filename' => $file->getClientOriginalName()]);
            }
        }

        $pack->update([
            'status'          => 'pending_review',
            'resubmitted_at'  => now(),
        ]);

        return back();
    }

    public function updatePrice(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'published', 422);

        $limits = $this->priceLimits();
        $data = $request->validate(
            ['price' => ['required', 'integer', 'min:' . $limits['min'], 'max:' . $limits['max']]],
            [
                'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
                'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
            ]
        );

        if ($this->moderationSettings()['existing_packs']) {
            return response()->json($this->upsertChangeRequest($pack, 'price', $data['price']));
        }

        $pack->update(['price' => $data['price']]);

        return response()->json(['price' => $pack->price]);
    }

    public function updateDescription(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'published', 422);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->moderationSettings()['existing_packs']) {
            return response()->json($this->upsertChangeRequest($pack, 'description', $data['description'] ?? null));
        }

        $pack->update(['description' => $data['description'] ?? null]);

        return response()->json(['description' => $pack->description]);
    }

    public function updateTitle(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'published', 422);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        if ($this->moderationSettings()['existing_packs']) {
            return response()->json($this->upsertChangeRequest($pack, 'title', $data['title']));
        }

        $pack->update(['title' => $data['title']]);

        return response()->json(['title' => $pack->title]);
    }

    public function updateCover(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'published', 422);

        $data = $request->validate([
            'photo_id' => ['required', 'integer'],
        ]);

        $photo = ContentPackPhoto::where('id', $data['photo_id'])
            ->where('content_pack_id', $pack->id)
            ->firstOrFail();

        $pack->update(['cover_path' => $photo->path]);

        return response()->json(['cover_url' => $pack->fresh()->cover_url]);
    }

    public function fixChangeRequest(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);

        $cr = $pack->pendingChangeRequest;
        abort_if(!$cr || $cr->status !== 'has_remarks', 422);

        $flaggedFields = $cr->flagged_fields ?? [];
        $limits = $this->priceLimits();

        $rules = [];
        if (in_array('title', $flaggedFields)) {
            $rules['title'] = ['sometimes', 'string', 'max:120'];
        }
        if (in_array('description', $flaggedFields)) {
            $rules['description'] = ['sometimes', 'nullable', 'string', 'max:2000'];
        }
        if (in_array('price', $flaggedFields)) {
            $rules['price'] = ['sometimes', 'integer', 'min:' . $limits['min'], 'max:' . $limits['max']];
        }

        $data = $request->validate($rules, [
            'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
            'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
        ]);

        // upsertChangeRequest queries DB fresh each call, so safe to call per field
        foreach ($flaggedFields as $field) {
            if (array_key_exists($field, $data)) {
                $this->upsertChangeRequest($pack, $field, $data[$field]);
            }
        }

        $pack->load('pendingChangeRequest');
        $newCr = $pack->pendingChangeRequest;

        return response()->json([
            'pending_change' => $newCr ? [
                'changed_fields'      => $newCr->changed_fields,
                'pending_title'       => $newCr->pending_title,
                'pending_description' => $newCr->pending_description,
                'pending_price'       => $newCr->pending_price,
                'status'              => $newCr->status,
                'flagged_fields'      => $newCr->flagged_fields ?? [],
                'field_comments'      => $newCr->field_comments ?? [],
                'admin_comment'       => $newCr->admin_comment,
            ] : null,
        ]);
    }

    public function publish(Request $request, ContentPack $pack): RedirectResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'approved', 422);

        $pack->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        NotifyFollowersJob::dispatch($pack->user, $pack);

        return back();
    }

    public function toggleVisibility(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'published', 422);

        $pack->update(['hidden_at' => $pack->hidden_at ? null : now()]);

        return response()->json(['hidden_at' => $pack->hidden_at?->toIso8601String()]);
    }

    public function dismissChangeRequest(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);

        $cr = $pack->pendingChangeRequest;
        if ($cr && $cr->status === 'rejected') {
            $cr->delete();
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, ContentPack $pack): RedirectResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);

        // Published pack with purchases → soft delete so buyers keep gallery access
        if ($pack->status === 'published' && $pack->purchases()->exists()) {
            $pack->delete();
            return back();
        }

        // Rejected packs — files already deleted by admin
        if ($pack->status !== 'rejected') {
            foreach ($pack->photos as $photo) {
                Storage::disk('local')->delete($photo->path);
            }
            Storage::disk('local')->deleteDirectory('content-packs/' . $pack->id);
        }

        $pack->forceDelete();

        return back();
    }

    public function indexForProfile(User $user, Request $request): JsonResponse
    {
        $authUser = auth()->user();
        $isOwner  = $authUser && $authUser->id === $user->id;

        $sort    = $request->input('sort', 'newest') === 'oldest' ? 'oldest' : 'newest';
        $perPage = 12;
        $cursor  = $request->input('cursor');

        $purchasedIds = [];
        if ($authUser && !$isOwner) {
            $purchasedIds = $authUser->contentPackPurchases()
                ->pluck('content_pack_id')
                ->toArray();
        }

        if ($isOwner) {
            $query = ContentPack::where('user_id', $user->id)
                ->with(['photos', 'latestReview', 'pendingChangeRequest']);

            if ($sort === 'newest') {
                if ($cursor) $query->where('id', '<', (int) $cursor);
                $query->orderByDesc('id');
            } else {
                if ($cursor) $query->where('id', '>', (int) $cursor);
                $query->orderBy('id');
            }

            $rows = $query->limit($perPage + 1)->get();
        } else {
            $query = ContentPack::where('user_id', $user->id)
                ->where('status', 'published')
                ->whereNull('hidden_at')
                ->with(['photos']);

            if ($sort === 'newest') {
                if ($cursor) $query->where('id', '<', (int) $cursor);
                $query->orderByDesc('id');
            } else {
                if ($cursor) $query->where('id', '>', (int) $cursor);
                $query->orderBy('id');
            }

            $rows = $query->limit($perPage + 1)->get();
        }

        $hasMore    = $rows->count() > $perPage;
        $rows       = $rows->take($perPage);
        $nextCursor = $hasMore ? $rows->last()?->id : null;

        $packs = $rows->map(fn (ContentPack $p) => $this->packToArray($p, $isOwner, in_array($p->id, $purchasedIds)));

        return response()->json([
            'packs'          => $packs,
            'purchased_ids'  => $purchasedIds,
            'next_cursor'    => $nextCursor,
            'has_more'       => $hasMore,
        ]);
    }

    private function packToArray(ContentPack $pack, bool $isOwner, bool $isPurchased = false): array
    {
        $base = [
            'id'           => $pack->id,
            'title'        => $pack->title,
            'description'  => $pack->description,
            'price'        => $pack->price,
            'status'       => $pack->status,
            'cover_url'    => $pack->cover_url,
            'photos_count' => $pack->photos->count(),
            'published_at' => $pack->published_at?->toIso8601String(),
            'hidden_at'    => $pack->hidden_at?->toIso8601String(),
        ];

        if ($isOwner || $isPurchased) {
            $base['photos'] = $pack->photos->map(fn ($ph) => [
                'id'  => $ph->id,
                'url' => $ph->url,
            ])->values();
        }

        if ($isOwner) {
            $review = $pack->latestReview;
            $base['latest_review'] = $review ? [
                'decision'          => $review->decision,
                'flagged_fields'    => $review->flagged_fields ?? [],
                'field_comments'    => $review->field_comments ?? [],
                'flagged_photo_ids' => $review->flagged_photo_ids ?? [],
                'photo_comments'    => $review->photo_comments ?? [],
            ] : null;

            $cr = $pack->pendingChangeRequest;
            $base['pending_change'] = $cr ? [
                'changed_fields'      => $cr->changed_fields,
                'pending_title'       => $cr->pending_title,
                'pending_description' => $cr->pending_description,
                'pending_price'       => $cr->pending_price,
                'status'              => $cr->status,
                'flagged_fields'      => $cr->flagged_fields ?? [],
                'field_comments'      => $cr->field_comments ?? [],
                'admin_comment'       => $cr->admin_comment,
            ] : null;
        }

        return $base;
    }

    private function compressAndStorePhoto($file, $packId)
    {
        $realPath = $file->getRealPath();
        $img = imagecreatefromstring(file_get_contents($realPath));

        if ($img) {
            // Fix orientation from EXIF
            $exif = function_exists('exif_read_data') ? @exif_read_data($realPath) : false;
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3: $img = imagerotate($img, 180, 0); break;
                    case 6: $img = imagerotate($img, -90, 0); break;
                    case 8: $img = imagerotate($img, 90, 0); break;
                }
            }

            $width  = imagesx($img);
            $height = imagesy($img);
            $maxDim = 1600;

            if ($width > $maxDim || $height > $maxDim) {
                $ratio = $width / $height;
                if ($ratio > 1) {
                    $newWidth  = $maxDim;
                    $newHeight = (int)($maxDim / $ratio);
                } else {
                    $newHeight = $maxDim;
                    $newWidth  = (int)($maxDim * $ratio);
                }
            } else {
                $newWidth  = $width;
                $newHeight = $height;
            }

            $newImg = imagecreatetruecolor($newWidth, $newHeight);
            $white  = imagecolorallocate($newImg, 255, 255, 255);
            imagefill($newImg, 0, 0, $white);
            imagecopyresampled($newImg, $img, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            $path = 'content-packs/' . $packId . '/' . time() . '_' . uniqid() . '.jpg';
            ob_start();
            imagejpeg($newImg, null, 70);
            $imageData = ob_get_clean();

            Storage::disk('local')->put($path, $imageData);
            imagedestroy($img);
            imagedestroy($newImg);

            return $path;
        }

        // Fallback to regular store if GD fails
        return $file->store('content-packs/' . $packId, 'local');
    }
}
