<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPhoto;
use App\Models\ContentPackPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
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
            ->with(['contentPack' => fn ($q) => $q->withTrashed()->with('user:id,name,avatar_path,active_frame_path')])
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

        $hasNewPacks = ContentPackPurchase::where('user_id', $userId)->whereNull('viewed_at')->exists();

        return Inertia::render('Gallery/Index', [
            'idols'         => $idols,
            'is_idol'       => (bool) $user->is_idol,
            'has_new_packs' => $hasNewPacks,
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
        $isNew   = $request->boolean('is_new');

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
                    'url'        => $ph->url,
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
            ->when($isNew, fn ($q) => $q->whereNull('content_pack_purchases.viewed_at'))
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
                'url'        => $ph->url,
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
        $userId  = auth()->id();
        $user    = auth()->user();
        $idolId  = $request->input('idol_id');
        $mine    = $request->boolean('mine');
        $isNew   = $request->boolean('is_new');
        $cursor  = $request->input('cursor');
        $perPage = 20;

        if ($mine && $user->is_idol) {
            $query = ContentPack::where('user_id', $userId)
                ->with('coverPhoto')
                ->withCount('photos')
                ->when($cursor, fn ($q) => $q->where('id', '<', $cursor))
                ->orderByDesc('id');
        } else {
            $query = ContentPack::withTrashed()
                ->whereHas('purchases', fn ($q) => $q->where('user_id', $userId)->when($isNew, fn ($q2) => $q2->whereNull('viewed_at')))
                ->with(['purchases' => fn ($q) => $q->where('user_id', $userId)])
                ->where('status', 'published')
                ->when($idolId, fn ($q) => $q->where('user_id', $idolId))
                ->with('coverPhoto')
                ->withCount('photos');

            if ($isNew) {
                // To sort by purchased_at correctly with pagination, we join purchases
                $query->join('content_pack_purchases', function ($join) use ($userId) {
                    $join->on('content_pack_purchases.content_pack_id', '=', 'content_packs.id')
                         ->where('content_pack_purchases.user_id', '=', $userId);
                })
                ->select('content_packs.*', 'content_pack_purchases.purchased_at', 'content_pack_purchases.id as purchase_id')
                ->when($cursor, fn ($q) => $q->where('content_pack_purchases.id', '<', $cursor))
                ->orderByDesc('content_pack_purchases.id');
            } else {
                $query->when($cursor, fn ($q) => $q->where('id', '<', $cursor))
                      ->orderByDesc('id');
            }
        }

        $packs   = $query->limit($perPage + 1)->get();
        $hasMore = $packs->count() > $perPage;
        if ($hasMore) {
            $packs = $packs->take($perPage);
        }

        return response()->json([
            'packs'       => $packs->map(fn ($p) => [
                'id'          => $p->id,
                'title'       => $p->title,
                'photo_count' => $p->photos_count,
                'cover_url'   => $p->cover_url,
                'is_new'      => !$mine && $p->purchases && $p->purchases->first() && is_null($p->purchases->first()->viewed_at),
            ])->values(),
            'next_cursor' => $hasMore ? ($isNew && !$mine ? $packs->last()?->purchase_id : $packs->last()?->id) : null,
            'has_more'    => $hasMore,
        ]);
    }
}
