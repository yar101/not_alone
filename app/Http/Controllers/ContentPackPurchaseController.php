<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentPackPurchaseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
        ]);

        $userId = $request->user()->id;
        $packIds = array_unique($data['items']);

        $packs = ContentPack::whereIn('id', $packIds)
            ->where('status', 'published')
            ->whereHas('user', fn ($q) => $q->where('is_banned', false))
            ->get()
            ->keyBy('id');

        $created = DB::transaction(function () use ($packIds, $packs, $userId) {
            $createdIds = [];
            foreach ($packIds as $packId) {
                $pack = $packs->get($packId);
                if (! $pack || $pack->user_id === $userId) {
                    continue;
                }

                $purchase = ContentPackPurchase::firstOrCreate(
                    [
                        'content_pack_id' => $pack->id,
                        'user_id' => $userId,
                    ],
                    [
                        'price_paid' => $pack->price,
                        'purchased_at' => now(),
                    ]
                );

                if ($purchase->wasRecentlyCreated) {
                    $createdIds[] = $packId;
                }
            }

            return $createdIds;
        });

        return response()->json(['success' => true, 'purchased' => $created]);
    }

    public function markAsViewed(Request $request, ContentPack $pack): JsonResponse
    {
        ContentPackPurchase::where('user_id', $request->user()->id)
            ->where('content_pack_id', $pack->id)
            ->whereNull('viewed_at')
            ->update(['viewed_at' => now()]);

        return response()->json(['success' => true]);
    }
}
