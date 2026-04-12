<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ContentPackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        abort_if(!$request->user()->is_idol, 403);

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price'       => ['required', 'integer', 'min:1', 'max:999999'],
            'photos'      => ['required', 'array', 'min:1', 'max:50'],
            'photos.*'    => ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
            'cover_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $pack = ContentPack::create([
            'user_id'     => $request->user()->id,
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'price'       => $data['price'],
            'status'      => 'pending_review',
        ]);

        $coverIndex = $data['cover_index'] ?? null;
        $coverPath  = null;

        foreach ($request->file('photos', []) as $index => $file) {
            $path = $file->store('content-packs/' . $pack->id, 'public');
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

        return back();
    }

    public function update(Request $request, ContentPack $pack): RedirectResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'has_remarks', 422);

        $review = $pack->latestReview;
        $flaggedFields = $review?->flagged_fields ?? [];
        $flaggedPhotoIds = $review?->flagged_photo_ids ?? [];

        $rules = [];
        if (in_array('title', $flaggedFields)) {
            $rules['title'] = ['sometimes', 'string', 'max:120'];
        }
        if (in_array('description', $flaggedFields)) {
            $rules['description'] = ['sometimes', 'nullable', 'string', 'max:2000'];
        }
        if (in_array('price', $flaggedFields)) {
            $rules['price'] = ['sometimes', 'integer', 'min:1', 'max:999999'];
        }
        if (!empty($flaggedPhotoIds)) {
            $rules['photos']   = ['sometimes', 'array'];
            $rules['photos.*'] = ['file', 'mimes:jpeg,jpg,png,webp', 'max:10240'];
            $rules['replace_photo_ids']   = ['sometimes', 'array'];
            $rules['replace_photo_ids.*'] = ['integer'];
            $rules['delete_photo_ids']    = ['sometimes', 'array'];
            $rules['delete_photo_ids.*']  = ['integer'];
        }

        $data = $request->validate($rules);

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
                Storage::disk('public')->delete($photo->path);
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
                Storage::disk('public')->delete($photo->path);
                // Store new
                $path = $file->store('content-packs/' . $pack->id, 'public');
                $photo->update(['path' => $path, 'original_filename' => $file->getClientOriginalName()]);
            }
        }

        $pack->update([
            'status'          => 'pending_review',
            'resubmitted_at'  => now(),
        ]);

        return back();
    }

    public function updateTitle(Request $request, ContentPack $pack): JsonResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'published', 422);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

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

    public function publish(Request $request, ContentPack $pack): RedirectResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if($pack->status !== 'approved', 422);

        $pack->update([
            'status'       => 'published',
            'published_at' => now(),
        ]);

        return back();
    }

    public function destroy(Request $request, ContentPack $pack): RedirectResponse
    {
        abort_if($pack->user_id !== $request->user()->id, 403);
        abort_if(in_array($pack->status, ['published', 'pending_review']), 422);

        // For rejected packs photos are already deleted by admin
        if ($pack->status !== 'rejected') {
            foreach ($pack->photos as $photo) {
                Storage::disk('public')->delete($photo->path);
            }
            Storage::disk('public')->deleteDirectory('content-packs/' . $pack->id);
        }

        $pack->delete();

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
                ->with(['photos', 'latestReview']);

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
        }

        return $base;
    }
}
