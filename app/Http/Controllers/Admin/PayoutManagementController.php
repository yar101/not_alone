<?php

namespace App\Http\Controllers\Admin;

use App\Enums\WalletTransactionType;
use App\Http\Controllers\Controller;
use App\Models\PayoutBatch;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\PayoutManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayoutManagementController extends Controller
{
    public function __construct(
        protected PayoutManagementService $payoutService
    ) {}

    /**
     * Главная страница раздела выплат.
     */
    public function index(Request $request): Response
    {
        $preview = $this->payoutService->previewNextPayout();

        // Historical batches
        $batches = PayoutBatch::query()
            ->with(['approver', 'items.idol'])
            ->latest()
            ->paginate(15, ['*'], 'batches_page')
            ->through(fn ($batch) => [
                'id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'status' => $batch->status,
                'total_amount' => (float) $batch->total_amount,
                'total_recipients' => $batch->total_recipients,
                'successful_count' => $batch->successful_count,
                'failed_count' => $batch->failed_count,
                'is_automated' => (bool) $batch->is_automated,
                'executed_at' => $batch->executed_at?->toIso8601String(),
                'created_at' => $batch->created_at->toIso8601String(),
                'approver_name' => $batch->approver?->name,
                'items' => $batch->items->map(fn ($item) => [
                    'id' => $item->id,
                    'idol_name' => $item->idol?->name ?? 'Айдол #'.$item->idol_id,
                    'amount' => (float) $item->amount,
                    'phone' => $item->phone,
                    'sbp_bank_id' => $item->sbp_bank_id,
                    'status' => $item->status,
                    'failure_reason' => $item->failure_reason,
                    'gateway_operation_id' => $item->gateway_operation_id,
                ]),
            ]);

        // Historical individual payout transactions
        $transactions = WalletTransaction::query()
            ->where('type', WalletTransactionType::Withdrawal)
            ->where('currency', 'pink')
            ->with(['user', 'wallet'])
            ->latest()
            ->paginate(20, ['*'], 'txs_page')
            ->through(fn ($tx) => [
                'id' => $tx->id,
                'user_id' => $tx->user_id,
                'user_name' => $tx->user?->name ?? 'Пользователь #'.$tx->user_id,
                'amount' => abs((float) $tx->amount),
                'status' => $tx->status->value,
                'status_label' => $tx->status->label(),
                'description' => $tx->description,
                'metadata' => $tx->metadata,
                'created_at' => $tx->created_at->toIso8601String(),
            ]);

        return Inertia::render('Admin/Payouts/Index', [
            'preview' => $preview,
            'batches' => $batches,
            'transactions' => $transactions,
        ]);
    }

    /**
     * API эндпоинт для обновления предпросмотра данных.
     */
    public function preview(): JsonResponse
    {
        return response()->json($this->payoutService->previewNextPayout());
    }

    /**
     * Получение детального среза начислений по конкретному айдолу.
     */
    public function breakdown(User $idol): JsonResponse
    {
        return response()->json($this->payoutService->getIdolBreakdown($idol));
    }

    /**
     * Переключение тумблера автоматических еженедельных выплат.
     */
    public function toggleAuto(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = (bool) $validated['enabled'];
        $this->payoutService->setAutoPayoutEnabled($enabled);

        $statusText = $enabled ? 'включен' : 'выключен (требуется ручная проверка)';

        return back()->with('success', "Автоматический режим выплат {$statusText}.");
    }

    /**
     * Отправка утверждённого реестра (батча) на выплату.
     */
    public function dispatchBatch(Request $request): RedirectResponse
    {
        $batchId = $request->input('batch_id');

        $batch = null;
        if ($batchId) {
            $batch = PayoutBatch::find($batchId);
        }

        if (! $batch) {
            $batch = $this->payoutService->createPendingBatch(isAutomated: false);
        }

        if ($batch->total_recipients === 0 || $batch->total_amount <= 0) {
            return back()->with('warning', 'В очереди нет получателей для выполнения выплаты.');
        }

        $executedBatch = $this->payoutService->dispatchBatch($batch, admin: auth('admin')->user());

        $msg = "Реестр #{$executedBatch->batch_number} отправлен: успешно {$executedBatch->successful_count}, ошибок {$executedBatch->failed_count}.";

        if ($executedBatch->failed_count > 0) {
            return back()->with('warning', $msg);
        }

        return back()->with('success', $msg);
    }

    /**
     * Выплата конкретному айдолу вне очереди.
     */
    public function dispatchSingle(Request $request, User $idol): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1.00'],
            'bypass_anti_fraud' => ['nullable', 'boolean'],
        ]);

        $amount = (float) $validated['amount'];
        $bypassAntiFraud = (bool) ($validated['bypass_anti_fraud'] ?? false);

        try {
            $result = $this->payoutService->dispatchSinglePayout(
                idol: $idol,
                amount: $amount,
                admin: auth('admin')->user(),
                bypassAntiFraud: $bypassAntiFraud
            );

            return back()->with('success', "Выплата на сумму {$amount} ₽ для айдола {$idol->name} успешно отправлена в СБП (Статус: {$result['status']}).");
        } catch (\Throwable $e) {
            return back()->with('error', 'Ошибка выплаты: '.$e->getMessage());
        }
    }

    /**
     * Ручная проверка статуса зависшей транзакции в шлюзе.
     */
    public function reconcile(WalletTransaction $transaction): RedirectResponse
    {
        $result = $this->payoutService->reconcilePayout($transaction);

        return back()->with('info', "Статус транзакции #{$transaction->id}: {$result['status']}");
    }
}
