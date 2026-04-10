<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\ContentPackPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(): Response
    {
        $userId = auth()->id();
        $user   = auth()->user();

        // Distinct idols from whom the user purchased content, ordered by most recent purchase
        $idols = ContentPackPurchase::where('user_id', $userId)
            ->with('contentPack.user:id,name,avatar_path')
            ->orderByDesc('purchased_at')
            ->get()
            ->map(fn ($purchase) => $purchase->contentPack?->user)
            ->filter()
            ->unique('id')
            ->map(fn ($idol) => [
                'id'         => $idol->id,
                'name'       => $idol->name,
                'avatar_url' => $idol->avatar_url,
            ])
            ->values();

        return Inertia::render('Gallery/Index', [
            'idols'   => $idols,
            'is_idol' => (bool) $user->is_idol,
        ]);
    }

    public function photos(Request $request): JsonResponse
    {
        $userId  = auth()->id();
        $user    = auth()->user();
        $idolId  = $request->input('idol_id');
        $mine    = $request->boolean('mine');
        $cursor  = $request->input('cursor'); // last seen photo id
        $perPage = 24;

        $packId  = $request->input('pack_id');

        // Idol viewing their own content pack photos
        if ($mine && $user->is_idol) {
            $query = ContentPackPhoto::query()
                ->join('content_packs', 'content_pack_photos.content_pack_id', '=', 'content_packs.id')
                ->where('content_packs.user_id', $userId)
                ->when($packId, fn ($q) => $q->where('content_pack_photos.content_pack_id', $packId))
                ->when($cursor, fn ($q) => $q->where('content_pack_photos.id', '<', $cursor))
                ->select(
                    'content_pack_photos.id',
                    'content_pack_photos.path',
                    'content_pack_photos.content_pack_id',
                    'content_packs.title as pack_title',
                    'content_packs.user_id as idol_id'
                )
                ->orderByDesc('content_pack_photos.id')
                ->limit($perPage + 1);

            $photos  = $query->get();
            $hasMore = $photos->count() > $perPage;
            if ($hasMore) {
                $photos = $photos->take($perPage);
            }
            $nextCursor = $hasMore ? $photos->last()?->id : null;

            return response()->json([
                'photos' => $photos->map(fn ($ph) => [
                    'id'         => $ph->id,
                    'url'        => \Illuminate\Support\Facades\Storage::url($ph->path),
                    'pack_id'    => $ph->content_pack_id,
                    'pack_title' => $ph->pack_title,
                    'idol_id'    => $ph->idol_id,
                ])->values(),
                'next_cursor' => $nextCursor,
                'has_more'    => $hasMore,
            ]);
        }

        // Join through purchases -> content_packs -> content_pack_photos
        $query = ContentPackPhoto::query()
            ->join('content_packs', 'content_pack_photos.content_pack_id', '=', 'content_packs.id')
            ->join('content_pack_purchases', function ($join) use ($userId) {
                $join->on('content_pack_purchases.content_pack_id', '=', 'content_packs.id')
                     ->where('content_pack_purchases.user_id', '=', $userId);
            })
            ->where('content_packs.status', 'published')
            ->when($idolId, fn ($q) => $q->where('content_packs.user_id', $idolId))
            ->when($packId, fn ($q) => $q->where('content_pack_photos.content_pack_id', $packId))
            ->when($cursor, fn ($q) => $q->where('content_pack_photos.id', '<', $cursor))
            ->select(
                'content_pack_photos.id',
                'content_pack_photos.path',
                'content_pack_photos.content_pack_id',
                'content_packs.title as pack_title',
                'content_packs.user_id as idol_id'
            )
            ->orderByDesc('content_pack_photos.id')
            ->limit($perPage + 1);

        $photos  = $query->get();
        $hasMore = $photos->count() > $perPage;
        if ($hasMore) {
            $photos = $photos->take($perPage);
        }

        $nextCursor = $hasMore ? $photos->last()?->id : null;

        return response()->json([
            'photos' => $photos->map(fn ($ph) => [
                'id'         => $ph->id,
                'url'        => \Illuminate\Support\Facades\Storage::url($ph->path),
                'pack_id'    => $ph->content_pack_id,
                'pack_title' => $ph->pack_title,
                'idol_id'    => $ph->idol_id,
            ])->values(),
            'next_cursor' => $nextCursor,
            'has_more'    => $hasMore,
        ]);
    }

    public function packs(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $user   = auth()->user();
        $idolId = $request->input('idol_id');
        $mine   = $request->boolean('mine');

        if ($mine && $user->is_idol) {
            $packs = ContentPack::where('user_id', $userId)
                ->with('photos:id,content_pack_id,path,sort_order')
                ->withCount('photos')
                ->orderByDesc('id')
                ->get();
        } else {
            $packs = ContentPack::query()
                ->whereHas('purchases', fn ($q) => $q->where('user_id', $userId))
                ->where('status', 'published')
                ->when($idolId, fn ($q) => $q->where('user_id', $idolId))
                ->with('photos:id,content_pack_id,path,sort_order')
                ->withCount('photos')
                ->orderByDesc('id')
                ->get();
        }

        return response()->json(
            $packs->map(fn ($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'photo_count' => $p->photos_count,
                'cover_url'   => $p->cover_url,
            ])->values()
        );
    }
}
