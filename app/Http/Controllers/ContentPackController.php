<?php

namespace App\Http\Controllers;

use App\Jobs\NotifyFollowersJob;
use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\ContentPackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ContentPackController extends Controller
{
    public function __construct(private ContentPackService $contentPackService) {}

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
            'new_packs' => (bool) (int) PlatformSetting::get('moderate_new_packs', 1),
            'existing_packs' => (bool) (int) PlatformSetting::get('moderate_existing_packs', 0),
        ];
    }

    /**
     * Settings moved out
     */
    public function store(Request $request): JsonResponse
    {
        abort_if(! $request->user()->is_idol, 403);

        $limits = $this->priceLimits();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:'.$limits['min'], 'max:'.$limits['max']],
            'photos' => ['required', 'array', 'min:1', 'max:50'],
            'photos.*' => ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'cover_index' => ['required', 'integer', 'min:0'],
        ], [
            'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
            'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
        ]);

        $moderation = $this->moderationSettings();
        $status = $moderation['new_packs'] ? 'pending_review' : 'approved';

        $pack = DB::transaction(function () use ($request, $data, $status) {
            $pack = ContentPack::create([
                'user_id' => $request->user()->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'status' => $status,
                'published_at' => null,
            ]);

            $coverIndex = $data['cover_index'] ?? null;
            $coverPath = null;
            $uploadJobs = [];

            foreach ($request->file('photos', []) as $index => $file) {
                [$tempPath, $finalPath] = $this->storeTempPhoto($file, $pack->id);

                $photo = ContentPackPhoto::create([
                    'content_pack_id' => $pack->id,
                    'path' => $tempPath,
                    'original_filename' => $file->getClientOriginalName(),
                    'sort_order' => $index,
                ]);

                $uploadJobs[] = [$tempPath, $finalPath, $photo->id];

                if ((int) $index === (int) $coverIndex) {
                    $coverPath = $tempPath;
                }
            }

            if ($coverPath) {
                $pack->update(['cover_path' => $coverPath]);
            }

            DB::afterCommit(function () use ($uploadJobs) {
                foreach ($uploadJobs as [$tempPath, $finalPath, $photoId]) {
                    \App\Jobs\ProcessImageUpload::dispatch($tempPath, $finalPath, ContentPackPhoto::class, $photoId, 'path');
                }
            });

            return $pack;
        });

        return response()->json(['id' => $pack->id, 'status' => $pack->status]);
    }

    public function update(Request $request, ContentPack $pack): RedirectResponse
    {
        $this->authorize('update', $pack);
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
            $rules['price'] = ['sometimes', 'integer', 'min:'.$limits['min'], 'max:'.$limits['max']];
        }
        if (! empty($flaggedPhotoIds)) {
            $rules['photos'] = ['sometimes', 'array'];
            $rules['photos.*'] = ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'];
            $rules['replace_photo_ids'] = ['sometimes', 'array'];
            $rules['replace_photo_ids.*'] = ['integer'];
            $rules['delete_photo_ids'] = ['sometimes', 'array'];
            $rules['delete_photo_ids.*'] = ['integer'];
        }

        $data = $request->validate($rules, [
            'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
            'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
        ]);

        $updateFields = [];
        if (isset($data['title'])) {
            $updateFields['title'] = $data['title'];
        }
        if (array_key_exists('description', $data)) {
            $updateFields['description'] = $data['description'];
        }
        if (isset($data['price'])) {
            $updateFields['price'] = $data['price'];
        }

        if (! empty($updateFields)) {
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
                Storage::delete($photo->path);
                if ($pack->cover_path === $photo->path) {
                    $pack->update(['cover_path' => null]);
                }
                $photo->delete();
            }
        }

        // Handle photo replacements
        if ($request->hasFile('photos') && ! empty($flaggedPhotoIds)) {
            $replaceIds = $data['replace_photo_ids'] ?? [];
            $files = $request->file('photos');

            foreach ($files as $i => $file) {
                $photoId = $replaceIds[$i] ?? null;
                if (! $photoId || ! in_array((int) $photoId, $flaggedPhotoIds)) {
                    continue;
                }
                $photo = ContentPackPhoto::find($photoId);
                if (! $photo || $photo->content_pack_id !== $pack->id) {
                    continue;
                }
                // Delete old file
                Storage::delete($photo->path);
                // Store new with async processing
                [$tempPath, $finalPath] = $this->storeTempPhoto($file, $pack->id);
                $photo->update(['path' => $tempPath, 'original_filename' => $file->getClientOriginalName()]);
                \App\Jobs\ProcessImageUpload::dispatch($tempPath, $finalPath, \App\Models\ContentPackPhoto::class, $photo->id, 'path');
            }
        }

        $pack->update([
            'status' => 'pending_review',
            'resubmitted_at' => now(),
        ]);

        return back();
    }

    public function updatePrice(Request $request, ContentPack $pack): JsonResponse
    {
        $this->authorize('update', $pack);
        abort_if($pack->status !== 'published', 422);

        $limits = $this->priceLimits();
        $data = $request->validate(
            ['price' => ['required', 'integer', 'min:'.$limits['min'], 'max:'.$limits['max']]],
            [
                'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
                'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
            ]
        );

        if ($this->moderationSettings()['existing_packs']) {
            return response()->json($this->contentPackService->upsertChangeRequest($pack, 'price', $data['price']));
        }

        $pack->update(['price' => $data['price']]);

        return response()->json(['price' => $pack->price]);
    }

    public function updateDescription(Request $request, ContentPack $pack): JsonResponse
    {
        $this->authorize('update', $pack);
        abort_if($pack->status !== 'published', 422);

        $data = $request->validate([
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($this->moderationSettings()['existing_packs']) {
            return response()->json($this->contentPackService->upsertChangeRequest($pack, 'description', $data['description'] ?? null));
        }

        $pack->update(['description' => $data['description'] ?? null]);

        return response()->json(['description' => $pack->description]);
    }

    public function updateTitle(Request $request, ContentPack $pack): JsonResponse
    {
        $this->authorize('update', $pack);
        abort_if($pack->status !== 'published', 422);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        if ($this->moderationSettings()['existing_packs']) {
            return response()->json($this->contentPackService->upsertChangeRequest($pack, 'title', $data['title']));
        }

        $pack->update(['title' => $data['title']]);

        return response()->json(['title' => $pack->title]);
    }

    public function updateCover(Request $request, ContentPack $pack): JsonResponse
    {
        $this->authorize('update', $pack);
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
        $this->authorize('update', $pack);

        $cr = $pack->pendingChangeRequest;
        abort_if(! $cr || $cr->status !== 'has_remarks', 422);

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
            $rules['price'] = ['sometimes', 'integer', 'min:'.$limits['min'], 'max:'.$limits['max']];
        }

        $data = $request->validate($rules, [
            'price.min' => "Цена слишком низкая, минимум {$limits['min']} ₽",
            'price.max' => "Цена слишком высокая, максимум {$limits['max']} ₽",
        ]);

        // upsertChangeRequest queries DB fresh each call, so safe to call per field
        foreach ($flaggedFields as $field) {
            if (array_key_exists($field, $data)) {
                $this->contentPackService->upsertChangeRequest($pack, $field, $data[$field]);
            }
        }

        $pack->load('pendingChangeRequest');
        $newCr = $pack->pendingChangeRequest;

        return response()->json([
            'pending_change' => $newCr ? [
                'changed_fields' => $newCr->changed_fields,
                'pending_title' => $newCr->pending_title,
                'pending_description' => $newCr->pending_description,
                'pending_price' => $newCr->pending_price,
                'status' => $newCr->status,
                'flagged_fields' => $newCr->flagged_fields ?? [],
                'field_comments' => $newCr->field_comments ?? [],
                'admin_comment' => $newCr->admin_comment,
            ] : null,
        ]);
    }

    public function publish(Request $request, ContentPack $pack): RedirectResponse
    {
        $this->authorize('update', $pack);
        abort_if($pack->status !== 'approved', 422);

        $pack->update([
            'status' => 'published',
            'published_at' => now(),
        ]);

        NotifyFollowersJob::dispatch($pack->user, $pack);

        return back();
    }

    public function toggleVisibility(Request $request, ContentPack $pack): JsonResponse
    {
        $this->authorize('update', $pack);
        abort_if($pack->status !== 'published', 422);

        $pack->update(['hidden_at' => $pack->hidden_at ? null : now()]);

        return response()->json(['hidden_at' => $pack->hidden_at?->toIso8601String()]);
    }

    public function dismissChangeRequest(Request $request, ContentPack $pack): JsonResponse
    {
        $this->authorize('update', $pack);

        $cr = $pack->pendingChangeRequest;
        if ($cr && $cr->status === 'rejected') {
            $cr->delete();
        }

        return response()->json(['success' => true]);
    }

    public function destroy(Request $request, ContentPack $pack): RedirectResponse
    {
        $this->authorize('update', $pack);

        // Published pack with purchases → soft delete so buyers keep gallery access
        if ($pack->status === 'published' && $pack->purchases()->exists()) {
            $pack->delete();

            return back();
        }

        // Rejected packs — files already deleted by admin
        if ($pack->status !== 'rejected') {
            foreach ($pack->photos as $photo) {
                Storage::delete($photo->path);
            }
            Storage::deleteDirectory('content-packs/'.$pack->id);
        }

        $pack->forceDelete();

        return back();
    }

    public function indexForProfile(User $user, Request $request): JsonResponse
    {
        $authUser = auth()->user();
        $isOwner = $authUser && $authUser->id === $user->id;

        $sort = $request->input('sort', 'newest') === 'oldest' ? 'oldest' : 'newest';
        $perPage = 12;
        $cursor = $request->input('cursor');

        $purchasedIds = [];
        if ($authUser && ! $isOwner) {
            $purchasedIds = $authUser->contentPackPurchases()
                ->pluck('content_pack_id')
                ->toArray();
        }

        if ($isOwner) {
            $query = ContentPack::where('user_id', $user->id)
                ->with(['photos', 'latestReview', 'pendingChangeRequest']);

            if ($sort === 'newest') {
                if ($cursor) {
                    $query->where('id', '<', (int) $cursor);
                }
                $query->orderByDesc('id');
            } else {
                if ($cursor) {
                    $query->where('id', '>', (int) $cursor);
                }
                $query->orderBy('id');
            }

            $rows = $query->limit($perPage + 1)->get();
        } else {
            $query = ContentPack::where('user_id', $user->id)
                ->where('status', 'published')
                ->whereNull('hidden_at')
                ->with(['photos']);

            if ($sort === 'newest') {
                if ($cursor) {
                    $query->where('id', '<', (int) $cursor);
                }
                $query->orderByDesc('id');
            } else {
                if ($cursor) {
                    $query->where('id', '>', (int) $cursor);
                }
                $query->orderBy('id');
            }

            $rows = $query->limit($perPage + 1)->get();
        }

        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);
        $nextCursor = $hasMore ? $rows->last()?->id : null;

        $packs = $rows->map(fn (ContentPack $p) => (new \App\Http\Resources\ContentPackResource($p, $isOwner, in_array($p->id, $purchasedIds)))->resolve());

        return response()->json([
            'packs' => $packs,
            'purchased_ids' => $purchasedIds,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ]);
    }

    private function storeTempPhoto($file, $packId)
    {
        $ext = $file->getClientOriginalExtension() ?: 'jpg';
        $tempPath = $file->storeAs('temp/content-packs/'.$packId, uniqid().'.'.$ext, config('filesystems.default'));
        $finalPath = 'content-packs/'.$packId.'/'.time().'_'.uniqid().'.jpg';

        return [$tempPath, $finalPath];
    }
}
