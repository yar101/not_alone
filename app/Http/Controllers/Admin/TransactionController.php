<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Events\NewNotification;
use App\Exceptions\InsufficientFundsException;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\WalletAdjustmentNotification;
use App\Services\WalletService;
use App\Traits\SafeBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    use SafeBroadcast;
    public function __construct(
        protected WalletService $walletService
    ) {}

    public function index(Request $request): Response
    {
        $query = WalletTransaction::query()->with(['user', 'wallet']);

        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($type = $request->input('type')) {
            if ($type !== 'all') {
                $query->where('type', $type);
            }
        }

        if ($status = $request->input('status')) {
            if ($status !== 'all') {
                $query->where('status', $status);
            }
        }

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('id', (int) $search);
                }
                $q->orWhere('description', 'ilike', "%{$search}%")
                  ->orWhere('idempotency_key', 'ilike', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'ilike', "%{$search}%")
                         ->orWhere('email', 'ilike', "%{$search}%");
                  });
            });
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $transactions = $query->orderByDesc('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WalletTransaction $tx) => [
                'id' => $tx->id,
                'wallet_id' => $tx->wallet_id,
                'user' => $tx->user ? [
                    'id' => $tx->user->id,
                    'name' => $tx->user->name,
                    'email' => $tx->user->email,
                    'avatar_url' => $tx->user->avatar_url,
                    'is_idol' => (bool) $tx->user->is_idol,
                ] : null,
                'type' => $tx->type->value,
                'type_label' => $tx->type->label(),
                'amount' => (float) $tx->amount,
                'balance_before' => (float) $tx->balance_before,
                'balance_after' => (float) $tx->balance_after,
                'held_balance_before' => (float) $tx->held_balance_before,
                'held_balance_after' => (float) $tx->held_balance_after,
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'description' => $tx->description,
                'idempotency_key' => $tx->idempotency_key,
                'metadata' => $tx->metadata,
                'reference_type' => $tx->reference_type,
                'reference_id' => $tx->reference_id,
                'created_at' => $tx->created_at?->format('d.m.Y H:i'),
                'created_at_iso' => $tx->created_at?->toISOString(),
            ]);

        // Type counts for tabs
        $typeCounts = [
            'all' => WalletTransaction::count(),
            'deposit' => WalletTransaction::where('type', WalletTransactionType::Deposit)->count(),
            'withdrawal' => WalletTransaction::where('type', WalletTransactionType::Withdrawal)->count(),
            'order_hold' => WalletTransaction::where('type', WalletTransactionType::OrderHold)->count(),
            'order_payout' => WalletTransaction::where('type', WalletTransactionType::OrderPayout)->count(),
            'order_refund' => WalletTransaction::where('type', WalletTransactionType::OrderRefund)->count(),
            'platform_fee' => WalletTransaction::where('type', WalletTransactionType::PlatformFee)->count(),
            'admin_adjustment' => WalletTransaction::where('type', WalletTransactionType::AdminAdjustment)->count(),
        ];

        // Global KPI Stats
        $kpi = [
            'total_deposits' => (float) WalletTransaction::where('type', WalletTransactionType::Deposit)
                ->where('status', WalletTransactionStatus::Completed)
                ->sum('amount'),
            'total_withdrawals' => (float) abs(WalletTransaction::where('type', WalletTransactionType::Withdrawal)
                ->where('status', WalletTransactionStatus::Completed)
                ->sum('amount')),
            'total_fees' => (float) abs(WalletTransaction::where('type', WalletTransactionType::PlatformFee)
                ->where('status', WalletTransactionStatus::Completed)
                ->sum('amount')),
            'total_count' => WalletTransaction::count(),
        ];

        $selectedUser = null;
        if ($userId) {
            $userModel = User::find($userId);
            if ($userModel) {
                $wallet = $this->walletService->getOrCreateWallet($userModel);
                $selectedUser = [
                    'id' => $userModel->id,
                    'name' => $userModel->name,
                    'email' => $userModel->email,
                    'avatar_url' => $userModel->avatar_url,
                    'is_idol' => (bool) $userModel->is_idol,
                    'balance' => (float) $wallet->balance,
                    'held_balance' => (float) $wallet->held_balance,
                ];
            }
        }

        return Inertia::render('Admin/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => [
                'user_id' => $userId,
                'search' => $request->input('search', ''),
                'type' => $request->input('type', ''),
                'status' => $request->input('status', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
            'typeCounts' => $typeCounts,
            'kpi' => $kpi,
            'selectedUser' => $selectedUser,
            'fees' => [
                'deposit_fee_percent' => (float) PlatformSetting::get('deposit_fee_percent', 0.0),
                'withdrawal_fee_percent' => (float) PlatformSetting::get('withdrawal_fee_percent', 0.0),
                'platform_fee_percent' => (float) PlatformSetting::get('platform_fee_percent', config('services.payments.platform_fee_percent', 10.0)),
            ],
            'types' => array_map(fn (WalletTransactionType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], WalletTransactionType::cases()),
            'statuses' => array_map(fn (WalletTransactionStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ], WalletTransactionStatus::cases()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'direction' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:10000000', 'regex:/^\d+(\.\d{1,2})?$/'],
            'type' => ['required', 'in:admin_adjustment,deposit,withdrawal'],
            'description' => ['required', 'string', 'min:3', 'max:500'],
            'notify_user' => ['sometimes', 'boolean'],
        ], [
            'user_id.required' => 'Выберите пользователя.',
            'user_id.exists' => 'Указанный пользователь не найден.',
            'amount.required' => 'Введите сумму операции.',
            'amount.min' => 'Сумма должна быть не менее 0.01 ₽.',
            'description.required' => 'Укажите причину или описание операции для аудита.',
        ]);

        $user = User::findOrFail($validated['user_id']);

        try {
            $tx = $this->walletService->adminAdjust(
                user: $user,
                amount: (float) $validated['amount'],
                direction: $validated['direction'],
                description: $validated['description'],
                adminId: auth('admin')->id(),
                type: $validated['type']
            );

            if ($request->boolean('notify_user')) {
                $user->notify(new WalletAdjustmentNotification($tx));
                $this->safeBroadcast(new NewNotification('private', $user->id));
            }

            return back()->with('success', "Транзакция #{$tx->id} успешно проведена.");
        } catch (InsufficientFundsException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        } catch (\DomainException|\InvalidArgumentException $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    public function show(WalletTransaction $transaction): JsonResponse
    {
        $transaction->load(['user', 'wallet']);

        return response()->json([
            'id' => $transaction->id,
            'wallet_id' => $transaction->wallet_id,
            'user' => $transaction->user ? [
                'id' => $transaction->user->id,
                'name' => $transaction->user->name,
                'email' => $transaction->user->email,
                'avatar_url' => $transaction->user->avatar_url,
                'is_idol' => (bool) $transaction->user->is_idol,
            ] : null,
            'type' => $transaction->type->value,
            'type_label' => $transaction->type->label(),
            'amount' => (float) $transaction->amount,
            'balance_before' => (float) $transaction->balance_before,
            'balance_after' => (float) $transaction->balance_after,
            'held_balance_before' => (float) $transaction->held_balance_before,
            'held_balance_after' => (float) $transaction->held_balance_after,
            'status' => $transaction->status->value,
            'status_label' => $transaction->status->label(),
            'description' => $transaction->description,
            'idempotency_key' => $transaction->idempotency_key,
            'metadata' => $transaction->metadata,
            'reference_type' => $transaction->reference_type,
            'reference_id' => $transaction->reference_id,
            'created_at' => $transaction->created_at?->format('d.m.Y H:i:s'),
            'updated_at' => $transaction->updated_at?->format('d.m.Y H:i:s'),
        ]);
    }
}
