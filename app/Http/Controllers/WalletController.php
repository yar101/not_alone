<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    public function show(Request $request): Response
    {
        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        $filter = $request->query('filter', 'all');
        $currency = $request->query('currency');

        $query = WalletTransaction::where('wallet_id', $wallet->id);
        $this->applyTransactionFilters($query, $filter, $currency);

        $page = $query
            ->orderByDesc('id')
            ->cursorPaginate(20)
            ->appends(array_merge($request->query(), ['tab' => 'history']));

        $display = $this->resolveDisplayPage($page->getCollection(), $wallet, $filter, $currency);

        $transactions = $page->through(fn ($tx) => $this->serializeTx($tx));

        $walletData = [
            'balance' => (float) $wallet->balance,
            'held_balance' => (float) $wallet->held_balance,
            'total_balance' => $wallet->total_balance,
            'currency' => $wallet->currency,
            'is_active' => $wallet->is_active,
        ];

        if ($user->is_idol) {
            $walletData['pink_balance'] = (float) $wallet->pink_balance;
            $walletData['pink_held_balance'] = (float) $wallet->pink_held_balance;
            $walletData['pink_total_balance'] = $wallet->pink_total_balance;
        }

        return Inertia::render('Wallet/Index', [
            'wallet' => $walletData,
            'transactions' => $transactions,
            'displayTransactions' => collect($display)->map(fn ($tx) => $this->serializeTx($tx))->values()->all(),
            'activeFilter' => $filter,
            'canDeposit' => app()->environment('local', 'testing') || (config('services.payments.mock_purchases', false) && ! app()->environment('production')),
            'isIdol' => (bool) $user->is_idol,
            'canWithdraw' => (bool) $user->is_idol,
            'depositFeePercent' => (float) PlatformSetting::get('deposit_fee_percent', config('services.payments.deposit_fee_percent', 4.0)),
            'withdrawalFeePercent' => (float) PlatformSetting::get('withdrawal_fee_percent', config('services.payments.withdrawal_fee_percent', 4.0)),
            'holdMinutes' => (int) PlatformSetting::get('order_dispute_window_minutes', 60),
        ]);
    }

    /**
     * Rows to render for the current page: order blocks are shown whole, on the page
     * whose window contains the block's newest transaction; other rows of the same
     * block are hidden elsewhere. Cursor links stay bound to the raw page window.
     *
     * @param  \Illuminate\Support\Collection<int, WalletTransaction>  $pageModels
     * @return array<int, WalletTransaction>
     */
    private function resolveDisplayPage(Collection $pageModels, Wallet $wallet, string $filter, ?string $currency): array
    {
        $pageIds = $pageModels->pluck('id');
        $pageOrderIds = $pageModels
            ->where('reference_type', Order::class)
            ->pluck('reference_id')
            ->unique()
            ->values();

        if ($pageOrderIds->isEmpty()) {
            return $pageModels->all();
        }

        $membersQuery = WalletTransaction::where('wallet_id', $wallet->id)
            ->where('reference_type', Order::class)
            ->whereIn('reference_id', $pageOrderIds->all());
        $this->applyTransactionFilters($membersQuery, $filter, $currency);
        $members = $membersQuery->orderByDesc('id')->get();

        $groups = $members->groupBy('reference_id');
        $ownGroupIds = [];
        foreach ($groups as $referenceId => $txs) {
            if ($pageIds->contains($txs->max('id'))) {
                $ownGroupIds[(string) $referenceId] = $txs->pluck('id')->all();
            }
        }

        $display = [];
        foreach ($pageModels as $tx) {
            $groupKey = (string) $tx->reference_id;
            if ($tx->reference_type === Order::class && isset($groups[$groupKey]) && ! isset($ownGroupIds[$groupKey])) {
                continue;
            }
            $display[$tx->id] = $tx;
        }

        foreach ($ownGroupIds as $txIds) {
            foreach ($txIds as $txId) {
                if (! isset($display[$txId])) {
                    $display[$txId] = $members->firstWhere('id', $txId);
                }
            }
        }

        krsort($display);

        return array_values($display);
    }

    private function serializeTx(WalletTransaction $tx): array
    {
        return [
            'id' => $tx->id,
            'type' => $tx->type->value,
            'type_label' => $tx->type->label(),
            'currency' => $tx->currency ?? 'blue',
            'amount' => (float) $tx->amount,
            'balance_before' => (float) $tx->balance_before,
            'balance_after' => (float) $tx->balance_after,
            'held_balance_before' => (float) $tx->held_balance_before,
            'held_balance_after' => (float) $tx->held_balance_after,
            'status' => $tx->status->value,
            'status_label' => $tx->status->label(),
            'description' => $tx->description,
            'metadata' => $tx->metadata,
            'created_at' => $tx->created_at->toISOString(),
            'order_id' => $tx->reference_type === Order::class ? (int) $tx->reference_id : null,
        ];
    }

    public function showJson(Request $request): JsonResponse
    {
        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);

        $filter = $request->query('filter', 'all');
        $query = WalletTransaction::where('wallet_id', $wallet->id);
        $this->applyTransactionFilters($query, $filter, $request->query('currency'));

        $transactions = $query
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn ($tx) => $this->serializeTx($tx));

        $walletData = [
            'balance' => (float) $wallet->balance,
            'held_balance' => (float) $wallet->held_balance,
            'total_balance' => $wallet->total_balance,
            'currency' => $wallet->currency,
            'is_active' => $wallet->is_active,
        ];

        if ($user->is_idol) {
            $walletData['pink_balance'] = (float) $wallet->pink_balance;
            $walletData['pink_held_balance'] = (float) $wallet->pink_held_balance;
            $walletData['pink_total_balance'] = $wallet->pink_total_balance;
        }

        return response()->json([
            'wallet' => $walletData,
            'transactions' => $transactions,
            'isIdol' => (bool) $user->is_idol,
            'canWithdraw' => (bool) $user->is_idol,
        ]);
    }

    private function applyTransactionFilters(Builder $query, string $filter, ?string $currencyFilter): void
    {
        if ($currencyFilter && in_array($currencyFilter, ['blue', 'pink'], true)) {
            $query->where('currency', $currencyFilter);
        }

        if ($filter === 'deposit' || $filter === 'deposits') {
            $query->where('type', \App\Enums\WalletTransactionType::Deposit);
        } elseif ($filter === 'holds') {
            $query->where(function ($q) {
                $q->where('type', \App\Enums\WalletTransactionType::OrderHold)
                    ->orWhere('type', \App\Enums\WalletTransactionType::OrderHoldRelease)
                    ->orWhere('type', \App\Enums\WalletTransactionType::OrderHoldCancelled)
                    ->orWhere(function ($sub) {
                        $sub->where('type', \App\Enums\WalletTransactionType::OrderPayout)
                            ->where('status', \App\Enums\WalletTransactionStatus::Pending);
                    });
            });
        } elseif ($filter === 'orders') {
            $query->where(function ($q) {
                $q->whereIn('type', [
                    \App\Enums\WalletTransactionType::OrderHold,
                    \App\Enums\WalletTransactionType::OrderPayout,
                    \App\Enums\WalletTransactionType::OrderRefund,
                    \App\Enums\WalletTransactionType::OrderHoldRelease,
                    \App\Enums\WalletTransactionType::OrderHoldCancelled,
                    \App\Enums\WalletTransactionType::OrderClawback,
                    \App\Enums\WalletTransactionType::CustomerServiceFee,
                ])->orWhere(function ($sub) {
                    $sub->where('type', \App\Enums\WalletTransactionType::PlatformFee)
                        ->where('reference_type', \App\Models\Order::class);
                });
            });
        } elseif ($filter === 'packs') {
            $query->where(function ($q) {
                $q->whereIn('type', [
                    \App\Enums\WalletTransactionType::PackPurchase,
                    \App\Enums\WalletTransactionType::PackSale,
                ])->orWhere(function ($sub) {
                    $sub->where('type', \App\Enums\WalletTransactionType::PlatformFee)
                        ->where('reference_type', \App\Models\ContentPack::class);
                });
            });
        } elseif ($filter === 'conversions') {
            $query->whereIn('type', [
                \App\Enums\WalletTransactionType::PinkToBlueConversion,
                \App\Enums\WalletTransactionType::BlueFromPinkConversion,
            ]);
        } elseif ($filter === 'donations') {
            $query->whereIn('type', [
                \App\Enums\WalletTransactionType::DonationSent,
                \App\Enums\WalletTransactionType::DonationReceived,
                \App\Enums\WalletTransactionType::DonationFee,
            ]);
        } elseif ($filter === 'withdrawal' || $filter === 'withdrawals') {
            $query->where('type', \App\Enums\WalletTransactionType::Withdrawal);
        }
    }

    public function deposit(Request $request): JsonResponse
    {
        if (! (app()->environment('local', 'testing') || (config('services.payments.mock_purchases', false) && ! app()->environment('production')))) {
            return response()->json(['error' => 'Прямое пополнение доступно только в тестовом режиме'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:100000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $rawKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (is_array($rawKey)) {
            $rawKey = reset($rawKey);
        }
        if ($rawKey !== null && (! is_string($rawKey) || strlen($rawKey) > 64)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['idempotency_key' => ['Длина ключа идемпотентности не должна превышать 64 символа.']],
                'error' => 'Длина ключа идемпотентности не должна превышать 64 символа.',
            ], 422);
        }
        $idempotencyKey = $rawKey ? (string) $rawKey : null;

        try {
            $tx = $this->walletService->deposit(
                $request->user(),
                (float) $data['amount'],
                'Пополнение через тестовую панель',
                $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'transaction' => $tx,
                'balance' => (float) $tx->balance_after,
            ]);
        } catch (\DomainException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'идемпотентност')) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function withdraw(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_idol) {
            return response()->json(['error' => 'Вывод средств доступен только айдолам.'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:100', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $rawKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (is_array($rawKey)) {
            $rawKey = reset($rawKey);
        }
        if ($rawKey !== null && (! is_string($rawKey) || strlen($rawKey) > 64)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['idempotency_key' => ['Длина ключа идемпотентности не должна превышать 64 символа.']],
                'error' => 'Длина ключа идемпотентности не должна превышать 64 символа.',
            ], 422);
        }
        $idempotencyKey = $rawKey ? (string) $rawKey : null;

        try {
            $tx = $this->walletService->withdraw(
                user: $user,
                amount: (float) $data['amount'],
                idempotencyKey: $idempotencyKey,
                description: 'Вывод средств на привязанную карту'
            );

            return response()->json([
                'success' => true,
                'transaction' => $tx,
                'balance' => (float) $user->wallet->fresh()->balance,
                'pink_balance' => (float) $tx->balance_after,
            ]);
        } catch (\DomainException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'идемпотентност')) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\App\Exceptions\InsufficientFundsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function convertPinkToBlue(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->is_idol) {
            return response()->json(['error' => 'Конвертация доступна только айдолам.'], 403);
        }

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ]);

        $rawKey = $request->header('X-Idempotency-Key') ?? $request->input('idempotency_key');
        if (is_array($rawKey)) {
            $rawKey = reset($rawKey);
        }
        if ($rawKey !== null && (! is_string($rawKey) || strlen($rawKey) > 64)) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => ['idempotency_key' => ['Длина ключа идемпотентности не должна превышать 64 символа.']],
                'error' => 'Длина ключа идемпотентности не должна превышать 64 символа.',
            ], 422);
        }
        $idempotencyKey = $rawKey ? (string) $rawKey : null;

        try {
            $result = $this->walletService->convertPinkToBlue(
                user: $user,
                amount: (float) $data['amount'],
                idempotencyKey: $idempotencyKey
            );

            return response()->json([
                'success' => true,
                'pink_balance' => $result['pink_balance'],
                'balance' => $result['balance'],
                'pink_transaction' => $result['pink_transaction'],
                'blue_transaction' => $result['blue_transaction'],
            ]);
        } catch (\DomainException $e) {
            if (str_contains(mb_strtolower($e->getMessage()), 'идемпотентност')) {
                return response()->json(['error' => $e->getMessage()], 409);
            }

            return response()->json(['error' => $e->getMessage()], 403);
        } catch (\App\Exceptions\InsufficientFundsException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }
}
