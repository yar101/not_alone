<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientFundsException;
use App\Models\ContentPack;
use App\Models\ContentPackPurchase;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContentPackPurchaseController extends Controller
{
    public function __construct(private ?WalletService $walletService = null)
    {
        $this->walletService = $this->walletService ?? app(WalletService::class);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['integer'],
        ]);

        $user = $request->user();
        $packIds = array_unique($data['items']);

        $packs = ContentPack::whereIn('id', $packIds)
            ->where('status', 'published')
            ->whereHas('user', fn ($q) => $q->where('is_banned', false))
            ->get()
            ->keyBy('id');

        try {
            $created = DB::transaction(function () use ($packIds, $packs, $user) {
                $createdIds = [];
                foreach ($packIds as $packId) {
                    $pack = $packs->get($packId);
                    if (! $pack || $pack->user_id === $user->id) {
                        continue;
                    }

                    $purchase = $this->walletService->purchaseContentPack($user, $pack);

                    if ($purchase->wasRecentlyCreated) {
                        $createdIds[] = $packId;
                    }
                }

                return $createdIds;
            });
        } catch (InsufficientFundsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

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
