<?php

namespace App\Http\Controllers;

use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentPackPurchaseController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items'   => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
        ]);

        $userId   = $request->user()->id;
        $packIds  = array_unique($data['items']);

        $alreadyPurchased = ContentPackPurchase::where('user_id', $userId)
            ->whereIn('content_pack_id', $packIds)
            ->pluck('content_pack_id')
            ->toArray();

        $packs = ContentPack::whereIn('id', $packIds)
            ->where('status', 'published')
            ->whereHas('user', fn($q) => $q->where('is_banned', false))
            ->get()
            ->keyBy('id');

        $created = [];
        foreach ($packIds as $packId) {
            if (in_array($packId, $alreadyPurchased)) {
                continue;
            }
            $pack = $packs->get($packId);
            if (!$pack) {
                continue;
            }
            ContentPackPurchase::create([
                'content_pack_id' => $pack->id,
                'user_id'         => $userId,
                'price_paid'      => $pack->price,
                'purchased_at'    => now(),
            ]);
            $created[] = $packId;
        }

        return response()->json(['success' => true, 'purchased' => $created]);
    }
}
