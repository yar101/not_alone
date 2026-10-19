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
            ->with(['user.wallet'])
            ->get();

        if ($packs->count() !== count($packIds)) {
            return response()->json(['error' => 'Один или несколько контент-паков недоступны для покупки.'], 422);
        }

        foreach ($packs as $pack) {
            if ($pack->user_id === $user->id) {
                return response()->json(['error' => 'Нельзя приобрести собственный контент-пак.'], 422);
            }
            if ($pack->user) {
                $wallet = $this->walletService->getOrCreateWallet($pack->user);
                $pack->user->setRelation('wallet', $wallet);
            }
        }

        $sortedPacks = $packs->sortBy(fn ($pack) => $pack->user?->wallet?->id ?? $pack->user_id);

        try {
            $created = DB::transaction(function () use ($sortedPacks, $user) {
                $createdIds = [];
                foreach ($sortedPacks as $pack) {
                    $purchase = $this->walletService->purchaseContentPack($user, $pack);

                    if ($purchase->wasRecentlyCreated) {
                        $createdIds[] = $pack->id;
                    }
                }

                return $createdIds;
            });
        } catch (InsufficientFundsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\DomainException $e) {
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
