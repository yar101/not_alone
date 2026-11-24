<?php

namespace App\Services;

use App\Contracts\MandarinGatewayInterface;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Admin;
use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\PayoutBatchItem;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayoutManagementService
{
    public function __construct(
        protected MandarinGatewayInterface $gateway
    ) {}

    public function getAutoPayoutEnabled(): bool
    {
        return (bool) PlatformSetting::get('weekly_payouts_auto_enabled', false);
    }

    public function setAutoPayoutEnabled(bool $enabled): void
    {
        PlatformSetting::set('weekly_payouts_auto_enabled', $enabled ? '1' : '0');
    }

    /**
     * Сбор данных и предпросмотр следующей выплаты.
     *
     * @return array<string, mixed>
     */
    public function previewNextPayout(bool $force = false): array
    {
        $minThreshold = (float) PlatformSetting::get('payout_min_threshold', 1000.00);
        $autoEnabled = $this->getAutoPayoutEnabled();

        $idols = User::query()
            ->where('is_idol', true)
            ->where('tax_status', 'self_employed')
            ->where('is_payout_ready', true)
            ->whereNotNull('payout_phone')
            ->whereNotNull('sbp_bank_id')
            ->with('wallet')
            ->get();

        $eligibleItems = [];
        $totalEligibleAmount = 0.00;
        $totalPendingAmount = 0.00;
        $totalEligibleCount = 0;

        foreach ($idols as $idol) {
            $wallet = $idol->wallet;
            if (! $wallet || ! $wallet->is_active) {
                continue;
            }

            $available = (float) $wallet->available_for_payout;
            if ($available <= 0.0) {
                continue;
            }

            $totalPendingAmount = (float) bcadd((string) $totalPendingAmount, (string) $available, 2);

            // Anti-fraud checks
            $requisitesChangedRecently = false;
            $requisitesHoursAgo = null;
            if ($idol->payout_requisites_changed_at && $idol->payout_requisites_changed_at->isAfter(now()->subHours(24))) {
                $requisitesChangedRecently = true;
                $requisitesHoursAgo = round(now()->diffInMinutes($idol->payout_requisites_changed_at) / 60, 1);
            }

            $accountTooNew = $idol->created_at && $idol->created_at->isAfter(now()->subHours(72));
            $accountHoursOld = $idol->created_at ? round($idol->created_at->diffInMinutes(now()) / 60, 1) : null;
            $chargebackDebt = (float) $wallet->chargeback_debt;

            $hasAntifraudWarning = $force ? false : ($requisitesChangedRecently || $accountTooNew);
            $meetsThreshold = ($available >= $minThreshold);
            $isReadyForAutoBatch = $meetsThreshold && ! $hasAntifraudWarning && ($chargebackDebt <= 0);

            if ($isReadyForAutoBatch) {
                $totalEligibleAmount = (float) bcadd((string) $totalEligibleAmount, (string) $available, 2);
                $totalEligibleCount++;
            }

            $eligibleItems[] = [
                'idol_id' => $idol->id,
                'name' => $idol->name,
                'nickname' => $idol->nickname ?? $idol->name,
                'email' => $idol->email,
                'avatar' => $idol->avatar,
                'tax_inn' => $idol->inn,
                'payout_phone' => $idol->payout_phone,
                'sbp_bank_id' => $idol->sbp_bank_id,
                'available_for_payout' => $available,
                'pink_balance' => (float) $wallet->pink_balance,
                'chargeback_debt' => $chargebackDebt,
                'meets_threshold' => $meetsThreshold,
                'is_ready_for_auto_batch' => $isReadyForAutoBatch,
                'has_antifraud_warning' => $hasAntifraudWarning,
                'antifraud_details' => [
                    'requisites_changed_recently' => $requisitesChangedRecently,
                    'requisites_hours_ago' => $requisitesHoursAgo,
                    'account_too_new' => $accountTooNew,
                    'account_hours_old' => $accountHoursOld,
                    'chargeback_debt' => $chargebackDebt,
                ],
            ];
        }

        // Sort: eligible for batch first, then by available descending
        usort($eligibleItems, function ($a, $b) {
            if ($a['is_ready_for_auto_batch'] !== $b['is_ready_for_auto_batch']) {
                return $b['is_ready_for_auto_batch'] <=> $a['is_ready_for_auto_batch'];
            }

            return $b['available_for_payout'] <=> $a['available_for_payout'];
        });

        // Calculate next scheduled Monday 06:00 UTC
        $now = now();
        $nextMonday = $now->copy()->next(1)->setTime(6, 0, 0);
        if ($now->isMonday() && $now->format('H:i') < '06:00') {
            $nextMonday = $now->copy()->setTime(6, 0, 0);
        }

        // Check if there is an active draft batch pending approval
        $pendingBatch = PayoutBatch::with('items.idol')
            ->where('status', 'pending_approval')
            ->latest()
            ->first();

        return [
            'auto_payout_enabled' => $autoEnabled,
            'min_payout_threshold' => $minThreshold,
            'total_eligible_amount' => $totalEligibleAmount,
            'total_eligible_count' => $totalEligibleCount,
            'total_pending_amount' => $totalPendingAmount,
            'total_idols_count' => count($eligibleItems),
            'next_scheduled_at' => $nextMonday->toIso8601String(),
            'pending_batch' => $pendingBatch ? [
                'id' => $pendingBatch->id,
                'batch_number' => $pendingBatch->batch_number,
                'status' => $pendingBatch->status,
                'total_amount' => (float) $pendingBatch->total_amount,
                'total_recipients' => $pendingBatch->total_recipients,
                'created_at' => $pendingBatch->created_at->toIso8601String(),
            ] : null,
            'idols' => $eligibleItems,
        ];
    }

    /**
     * Детализация начислений («за что начислено в деталях»).
     *
     * @return array<string, mixed>
     */
    public function getIdolBreakdown(User $idol): array
    {
        // Query recent pink earning transactions
        $transactions = WalletTransaction::where('user_id', $idol->id)
            ->where('currency', 'pink')
            ->whereIn('type', [
                WalletTransactionType::OrderPayout,
                WalletTransactionType::PackSale,
                WalletTransactionType::DonationReceived,
            ])
            ->where('status', WalletTransactionStatus::Completed)
            ->latest()
            ->limit(100)
            ->get();

        $items = [];
        $totals = [
            'orders' => 0.00,
            'packs' => 0.00,
            'donations' => 0.00,
            'total' => 0.00,
        ];

        foreach ($transactions as $tx) {
            $meta = $tx->metadata ?? [];
            $amount = abs((float) ($meta['net_payout'] ?? $tx->amount));

            $detail = [
                'id' => $tx->id,
                'type' => $tx->type->value,
                'type_label' => $tx->type->label(),
                'amount' => $amount,
                'date' => $tx->created_at->toIso8601String(),
                'description' => $tx->description,
            ];

            if ($tx->type === WalletTransactionType::OrderPayout) {
                $orderId = $meta['order_id'] ?? $tx->reference_id;
                $order = $orderId ? Order::with(['items.service', 'customer'])->find($orderId) : null;
                $firstItem = $order?->items?->first();
                $serviceTitle = $firstItem?->service?->name ?? 'Услуга #'.$orderId;

                $detail['order'] = [
                    'id' => $orderId,
                    'service_title' => $serviceTitle,
                    'customer_name' => $order?->customer?->name ?? 'Клиент',
                    'gross_amount' => (float) ($meta['gross_amount'] ?? $tx->amount),
                    'fee_amount' => (float) ($meta['fee_amount'] ?? 0.0),
                    'net_amount' => $amount,
                ];
                $totals['orders'] = (float) bcadd((string) $totals['orders'], (string) $amount, 2);
            } elseif ($tx->type === WalletTransactionType::PackSale) {
                $detail['pack'] = [
                    'id' => $meta['pack_id'] ?? $tx->reference_id,
                    'title' => $meta['pack_title'] ?? 'Контент-пак',
                    'gross_amount' => (float) ($meta['gross_amount'] ?? $tx->amount),
                    'fee_amount' => (float) ($meta['fee_amount'] ?? 0.0),
                    'net_amount' => $amount,
                ];
                $totals['packs'] = (float) bcadd((string) $totals['packs'], (string) $amount, 2);
            } elseif ($tx->type === WalletTransactionType::DonationReceived) {
                $detail['donation'] = [
                    'donor_name' => $meta['donor_name'] ?? 'Пользователь',
                    'message' => $meta['message'] ?? null,
                    'net_amount' => $amount,
                ];
                $totals['donations'] = (float) bcadd((string) $totals['donations'], (string) $amount, 2);
            }

            $totals['total'] = (float) bcadd((string) $totals['total'], (string) $amount, 2);
            $items[] = $detail;
        }

        return [
            'idol' => [
                'id' => $idol->id,
                'name' => $idol->name,
                'nickname' => $idol->nickname ?? $idol->name,
                'available_for_payout' => (float) ($idol->wallet?->available_for_payout ?? 0.0),
            ],
            'totals' => $totals,
            'items' => $items,
        ];
    }

    /**
     * Формирование предварительного реестра (батча) со статусом pending_approval.
     */
    public function createPendingBatch(bool $isAutomated = false, bool $force = false): PayoutBatch
    {
        $preview = $this->previewNextPayout(force: $force);

        return DB::transaction(function () use ($preview, $isAutomated) {
            $batchNumber = sprintf('PAYOUT-%s-%s', now()->format('Y\WW'), now()->format('His'));

            $batch = PayoutBatch::create([
                'batch_number' => $batchNumber,
                'status' => 'pending_approval',
                'total_amount' => $preview['total_eligible_amount'],
                'total_recipients' => $preview['total_eligible_count'],
                'successful_count' => 0,
                'failed_count' => 0,
                'is_automated' => $isAutomated,
                'scheduled_for' => now(),
                'metadata' => [
                    'preview_snapshot_at' => now()->toIso8601String(),
                ],
            ]);

            foreach ($preview['idols'] as $item) {
                if (! $item['is_ready_for_auto_batch']) {
                    continue;
                }

                PayoutBatchItem::create([
                    'payout_batch_id' => $batch->id,
                    'idol_id' => $item['idol_id'],
                    'amount' => $item['available_for_payout'],
                    'phone' => $item['payout_phone'],
                    'sbp_bank_id' => $item['sbp_bank_id'],
                    'status' => 'pending',
                ]);
            }

            return $batch;
        });
    }

    /**
     * Отправка утверждённого реестра (батча) на исполнение через Mandarin BaaS.
     */
    public function dispatchBatch(PayoutBatch $batch, ?Admin $admin = null): PayoutBatch
    {
        $batch->update([
            'status' => 'processing',
            'approved_by' => $admin?->id,
            'executed_at' => now(),
        ]);

        $items = $batch->items()->where('status', 'pending')->with('idol.wallet')->get();
        $successful = 0;
        $failed = 0;

        foreach ($items as $item) {
            $idol = $item->idol;
            if (! $idol) {
                $item->update(['status' => 'failed', 'failure_reason' => 'Idol user missing']);
                $failed++;

                continue;
            }

            $lockKey = "payout:idol:{$idol->id}";
            $lock = Cache::lock($lockKey, 60);

            if (! $lock->get()) {
                $item->update(['status' => 'failed', 'failure_reason' => 'Idol payout lock busy']);
                $failed++;

                continue;
            }

            try {
                $result = $this->executeSinglePayoutTransaction(
                    idol: $idol,
                    amount: (float) $item->amount,
                    batchId: $batch->id,
                    adminId: $admin?->id,
                    isOutOfTurn: false
                );

                $item->update([
                    'status' => $result['status'] === WalletTransactionStatus::Completed->value ? 'completed' : 'processing',
                    'wallet_transaction_id' => $result['transaction_id'],
                    'gateway_operation_id' => $result['gateway_operation_id'] ?? null,
                ]);

                $successful++;
            } catch (\Throwable $e) {
                Log::error("PayoutBatch #{$batch->id} item failed for idol #{$idol->id}: ".$e->getMessage());
                $item->update([
                    'status' => 'failed',
                    'failure_reason' => $e->getMessage(),
                ]);
                $failed++;
            } finally {
                $lock->release();
            }
        }

        $finalStatus = match (true) {
            $failed === 0 => 'completed',
            $successful > 0 => 'partially_failed',
            default => 'failed',
        };

        $batch->update([
            'status' => $finalStatus,
            'successful_count' => $successful,
            'failed_count' => $failed,
        ]);

        return $batch;
    }

    /**
     * Выплата конкретному айдолу «вне очереди».
     *
     * @return array<string, mixed>
     */
    public function dispatchSinglePayout(
        User $idol,
        float $amount,
        ?Admin $admin = null,
        bool $bypassAntiFraud = false
    ): array {
        if ($amount < 1.0) {
            throw new \InvalidArgumentException('Минимальная сумма выплаты: 1.00 ₽.');
        }

        if (! $idol->is_idol || $idol->tax_status !== 'self_employed') {
            throw new \InvalidArgumentException('Выплата доступна только зарегистрированным самозанятым айдолам.');
        }

        if (empty($idol->payout_phone) || empty($idol->sbp_bank_id)) {
            throw new \InvalidArgumentException('У айдола не заполнены реквизиты СБП (телефон или банк).');
        }

        // Anti-fraud validation
        $hasAntifraud = ($idol->payout_requisites_changed_at && $idol->payout_requisites_changed_at->isAfter(now()->subHours(24)))
            || ($idol->created_at && $idol->created_at->isAfter(now()->subHours(72)));

        if ($hasAntifraud && ! $bypassAntiFraud) {
            throw new \InvalidArgumentException('Требуется подтверждение снятия антифрод-ограничения (недавняя смена реквизитов или возраст аккаунта < 72 ч).');
        }

        $lockKey = "payout:idol:{$idol->id}";
        $lock = Cache::lock($lockKey, 60);

        if (! $lock->get()) {
            throw new \RuntimeException('Операция выплаты для данного айдола уже обрабатывается.');
        }

        try {
            return $this->executeSinglePayoutTransaction(
                idol: $idol,
                amount: $amount,
                batchId: null,
                adminId: $admin?->id,
                isOutOfTurn: true
            );
        } finally {
            $lock->release();
        }
    }

    /**
     * Синхронизация статуса конкретной зависшей транзакции выплаты со шлюзом Mandarin.
     *
     * @return array<string, mixed>
     */
    public function reconcilePayout(WalletTransaction $tx): array
    {
        $meta = $tx->metadata ?? [];
        $opId = $meta['gateway_operation_id'] ?? null;

        if (! $opId) {
            return [
                'status' => $tx->status->value,
                'message' => 'Отсутствует ID операции в шлюзе.',
            ];
        }

        $statusData = $this->gateway->getOperationStatus($opId);
        $gwStatus = $statusData['status'] ?? 'pending_bank';

        if (in_array($gwStatus, ['success', 'settled'], true)) {
            DB::transaction(function () use ($tx, $meta) {
                $wallet = Wallet::where('id', $tx->wallet_id)->lockForUpdate()->first();
                if ($wallet) {
                    $amount = abs((float) $tx->amount);
                    $newHeld = max(0.00, (float) bcsub((string) $wallet->pink_held_balance, (string) $amount, 2));
                    $wallet->forceFill(['pink_held_balance' => $newHeld])->save();
                }

                $meta['payout_status'] = 'settled';
                $tx->update([
                    'status' => WalletTransactionStatus::Completed,
                    'metadata' => $meta,
                ]);
            });
        } elseif (in_array($gwStatus, ['failed', 'rejected', 'declined'], true)) {
            DB::transaction(function () use ($tx, $meta, $statusData) {
                $wallet = Wallet::where('id', $tx->wallet_id)->lockForUpdate()->first();
                if ($wallet) {
                    $amount = abs((float) $tx->amount);
                    $newHeld = max(0.00, (float) bcsub((string) $wallet->pink_held_balance, (string) $amount, 2));
                    $newBal = (float) bcadd((string) $wallet->pink_balance, (string) $amount, 2);
                    $wallet->forceFill([
                        'pink_balance' => $newBal,
                        'pink_held_balance' => $newHeld,
                    ])->save();
                }

                $meta['payout_status'] = 'failed';
                $meta['failure_reason'] = $statusData['raw']['error_message'] ?? 'Отклонено банком';
                $tx->update([
                    'status' => WalletTransactionStatus::Failed,
                    'metadata' => $meta,
                ]);
            });
        }

        return [
            'status' => $tx->fresh()->status->value,
            'gateway_status' => $gwStatus,
        ];
    }

    /**
     * Внутреннее исполнение финансовой транзакции СБП.
     *
     * @return array<string, mixed>
     */
    protected function executeSinglePayoutTransaction(
        User $idol,
        float $amount,
        ?int $batchId = null,
        ?int $adminId = null,
        bool $isOutOfTurn = false
    ): array {
        return DB::transaction(function () use ($idol, $amount, $batchId, $adminId, $isOutOfTurn) {
            $wallet = Wallet::where('user_id', $idol->id)->lockForUpdate()->first();
            if (! $wallet || ! $wallet->is_active) {
                throw new \RuntimeException('Кошелек айдола не найден или заблокирован.');
            }

            $available = (float) $wallet->available_for_payout;
            if ($amount > $available) {
                throw new \RuntimeException("Недостаточно средств: запрошено {$amount} ₽, доступно {$available} ₽.");
            }

            $idempotencyKey = sprintf('payout_sbp_%d_%s', $idol->id, now()->format('Ymd_His_u'));

            if (WalletTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
                throw new \RuntimeException('Дублирующий запрос выплаты.');
            }

            // Move funds from pink_balance to pink_held_balance during bank transfer
            $balBefore = (float) $wallet->pink_balance;
            $heldBefore = (float) $wallet->pink_held_balance;

            $balAfter = (float) bcsub((string) $balBefore, (string) $amount, 2);
            $heldAfter = (float) bcadd((string) $heldBefore, (string) $amount, 2);

            $wallet->forceFill([
                'pink_balance' => $balAfter,
                'pink_held_balance' => $heldAfter,
            ])->save();

            $desc = $isOutOfTurn
                ? 'Внеочередная выплата заработка по СБП (0.00 ₽ комиссия)'
                : 'Автоматическая выплата заработка по СБП (0.00 ₽ комиссия)';

            $tx = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $idol->id,
                'type' => WalletTransactionType::Withdrawal,
                'amount' => -$amount,
                'balance_before' => $balBefore,
                'balance_after' => $balAfter,
                'held_balance_before' => $heldBefore,
                'held_balance_after' => $heldAfter,
                'status' => WalletTransactionStatus::PendingBank,
                'currency' => 'pink',
                'idempotency_key' => $idempotencyKey,
                'description' => $desc,
                'metadata' => [
                    'payout_method' => 'sbp',
                    'phone' => $idol->payout_phone,
                    'sbp_bank_id' => $idol->sbp_bank_id,
                    'gross_amount' => $amount,
                    'fee_amount' => 0.00,
                    'net_payout' => $amount,
                    'payout_status' => 'pending_bank',
                    'payout_batch_id' => $batchId,
                    'is_out_of_turn' => $isOutOfTurn,
                    'dispatched_by_admin_id' => $adminId,
                ],
            ]);

            // Call Mandarin Gateway for SBP payout
            $gatewayResult = $this->gateway->executePayout(
                idol: $idol,
                amount: $amount,
                requisites: [
                    'type' => 'sbp',
                    'phone' => $idol->payout_phone,
                    'bank_id' => $idol->sbp_bank_id,
                ],
                idempotencyKey: $idempotencyKey
            );

            $opId = $gatewayResult['operation_id'] ?? $gatewayResult['payout_id'] ?? null;
            $gwStatus = $gatewayResult['status'] ?? 'pending_bank';

            $meta = $tx->metadata ?? [];
            $meta['gateway_operation_id'] = $opId;
            $meta['fns_receipt_url'] = $gatewayResult['fns_receipt_url'] ?? null;

            if (in_array($gwStatus, ['success', 'settled'], true)) {
                $tx->update([
                    'status' => WalletTransactionStatus::Completed,
                    'metadata' => array_merge($meta, ['payout_status' => 'settled']),
                ]);

                $wallet->forceFill([
                    'pink_held_balance' => max(0.00, (float) bcsub((string) $wallet->pink_held_balance, (string) $amount, 2)),
                ])->save();
            } else {
                $tx->update(['metadata' => $meta]);
            }

            return [
                'amount' => $amount,
                'transaction_id' => $tx->id,
                'status' => $tx->status->value,
                'gateway_operation_id' => $opId,
            ];
        });
    }
}
