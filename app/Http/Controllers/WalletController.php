<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->latest('id')
            ->paginate(20)
            ->through(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => $tx->type->label(),
                'amount' => (float) $tx->amount,
                'balance_after' => (float) $tx->balance_after,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'description' => $tx->description,
                'created_at' => $tx->created_at->toISOString(),
            ]);

        return Inertia::render('Wallet/Index', [
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'held_balance' => (float) $wallet->held_balance,
                'total_balance' => $wallet->total_balance,
                'currency' => $wallet->currency,
                'is_active' => $wallet->is_active,
            ],
            'transactions' => $transactions,
            'canDeposit' => app()->environment('local', 'testing') || config('services.payments.mock_purchases', true),
            'isIdol' => (bool) $user->is_idol,
            'canWithdraw' => (bool) $user->is_idol,
        ]);
    }

    public function showJson(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($tx) => [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => $tx->type->label(),
                'amount' => (float) $tx->amount,
                'balance_after' => (float) $tx->balance_after,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'description' => $tx->description,
                'created_at' => $tx->created_at->toISOString(),
            ]);

        return response()->json([
            'wallet' => [
                'balance' => (float) $wallet->balance,
                'held_balance' => (float) $wallet->held_balance,
                'total_balance' => $wallet->total_balance,
                'currency' => $wallet->currency,
                'is_active' => $wallet->is_active,
            ],
            'transactions' => $transactions,
            'isIdol' => (bool) $user->is_idol,
            'canWithdraw' => (bool) $user->is_idol,
        ]);
    }

    public function deposit(Request $request): JsonResponse
    {
        if (! (app()->environment('local', 'testing') || config('services.payments.mock_purchases', true))) {
            return response()->json(['error' => 'Прямое пополнение доступно только в тестовом режиме'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:100000'],
        ]);

        $tx = $this->walletService->deposit(
            $request->user(),
            (float) $data['amount'],
            'Пополнение через тестовую панель'
        );

        return response()->json([
            'success' => true,
            'transaction' => $tx,
            'balance' => (float) $tx->balance_after,
        ]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_idol) {
            return response()->json(['error' => 'Вывод средств доступен только айдолам.'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:1000000'],
        ]);

        try {
            $tx = $this->walletService->withdraw(
                user: $user,
                amount: (float) $data['amount'],
                description: 'Вывод средств на привязанную карту'
            );

            return response()->json([
                'success' => true,
                'transaction' => $tx,
                'balance' => (float) $tx->balance_after,
            ]);
        } catch (\DomainException $e) {
            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\App\Exceptions\InsufficientFundsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
