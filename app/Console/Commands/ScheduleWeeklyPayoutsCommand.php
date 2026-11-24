<?php

namespace App\Console\Commands;

use App\Contracts\MandarinGatewayInterface;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ScheduleWeeklyPayoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:schedule-weekly-payouts {--force : Bypass 24h requisites and 72h new account holds for testing}';

    /**
     * The console command description.
     */
    protected $description = 'Dispatch weekly automated SBP payouts on Mondays for self-employed idols (>= 1 000 ₽, 0% fee)';

    public function handle(MandarinGatewayInterface $gateway): int
    {
        $this->info('Starting automated weekly SBP payouts for self-employed idols...');

        $minThreshold = (float) PlatformSetting::get('payout_min_threshold', 1000.00);
        $isForce = (bool) $this->option('force');

        // Query eligible self-employed idols
        $idolsQuery = User::where('is_idol', true)
            ->where('tax_status', 'self_employed')
            ->where('is_payout_ready', true)
            ->whereNotNull('payout_phone')
            ->whereNotNull('sbp_bank_id');

        if (! $isForce) {
            // Anti-fraud: requisites not modified in the last 24 hours
            $idolsQuery->where(function ($q) {
                $q->whereNull('payout_requisites_changed_at')
                    ->orWhere('payout_requisites_changed_at', '<=', now()->subHours(24));
            });

            // Anti-fraud: 72 hours freeze for brand new accounts
            $idolsQuery->where('created_at', '<=', now()->subHours(72));
        }

        $idols = $idolsQuery->get();
        $dispatchedCount = 0;
        $totalDispatchedAmount = 0.00;

        foreach ($idols as $idol) {
            $lockKey = "payout:idol:{$idol->id}";
            $lock = Cache::lock($lockKey, 60);

            if (! $lock->get()) {
                $this->warn("Idol #{$idol->id} payout is locked by another process; skipping");

                continue;
            }

            try {
                $result = DB::transaction(function () use ($idol, $minThreshold, $gateway) {
                    $wallet = Wallet::where('user_id', $idol->id)->lockForUpdate()->first();
                    if (! $wallet || ! $wallet->is_active) {
                        return null;
                    }

                    $available = (float) $wallet->available_for_payout;
                    if ($available < $minThreshold) {
                        return null;
                    }

                    $idempotencyKey = sprintf('payout_sbp_%d_%s', $idol->id, now()->format('Ymd_His'));

                    // Check if an identical payout transaction already exists
                    if (WalletTransaction::where('idempotency_key', $idempotencyKey)->exists()) {
                        return null;
                    }

                    // Move funds from pink_balance into pink_held_balance during dispatch
                    $balBefore = (float) $wallet->pink_balance;
                    $heldBefore = (float) $wallet->pink_held_balance;

                    $balAfter = (float) bcsub((string) $balBefore, (string) $available, 2);
                    $heldAfter = (float) bcadd((string) $heldBefore, (string) $available, 2);

                    $wallet->forceFill([
                        'pink_balance' => $balAfter,
                        'pink_held_balance' => $heldAfter,
                    ])->save();

                    $tx = WalletTransaction::create([
                        'wallet_id' => $wallet->id,
                        'user_id' => $idol->id,
                        'type' => WalletTransactionType::Withdrawal,
                        'amount' => -$available,
                        'balance_before' => $balBefore,
                        'balance_after' => $balAfter,
                        'held_balance_before' => $heldBefore,
                        'held_balance_after' => $heldAfter,
                        'status' => WalletTransactionStatus::PendingBank,
                        'currency' => 'pink',
                        'idempotency_key' => $idempotencyKey,
                        'description' => 'Автоматическая выплата заработка по СБП (0.00 ₽ комиссия)',
                        'metadata' => [
                            'payout_method' => 'sbp',
                            'phone' => $idol->payout_phone,
                            'sbp_bank_id' => $idol->sbp_bank_id,
                            'gross_amount' => $available,
                            'fee_amount' => 0.00,
                            'net_payout' => $available,
                            'payout_status' => 'pending_bank',
                        ],
                    ]);

                    // Call Mandarin Gateway for SBP payout
                    $gatewayResult = $gateway->executePayout(
                        idol: $idol,
                        amount: $available,
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

                    if (in_array($gwStatus, ['success', 'settled'])) {
                        // Immediately settled: clear pink_held_balance
                        $tx->update([
                            'status' => WalletTransactionStatus::Completed,
                            'metadata' => array_merge($meta, ['payout_status' => 'settled']),
                        ]);

                        $wallet->forceFill([
                            'pink_held_balance' => max(0.00, (float) bcsub((string) $wallet->pink_held_balance, (string) $available, 2)),
                        ])->save();
                    } else {
                        $tx->update(['metadata' => $meta]);
                    }

                    return [
                        'amount' => $available,
                        'transaction_id' => $tx->id,
                        'status' => $tx->status->value,
                    ];
                });

                if ($result) {
                    $dispatchedCount++;
                    $totalDispatchedAmount = (float) bcadd((string) $totalDispatchedAmount, (string) $result['amount'], 2);
                    $this->line("  ✓ Idol #{$idol->id}: Dispatched {$result['amount']} ₽ (Tx #{$result['transaction_id']}, Status: {$result['status']})");
                }
            } catch (\Throwable $e) {
                Log::error("Failed to execute SBP payout for idol #{$idol->id}: ".$e->getMessage());
                $this->error("  ✗ Idol #{$idol->id}: Error: {$e->getMessage()}");
            } finally {
                $lock->release();
            }
        }

        $this->info("Completed weekly payouts: {$dispatchedCount} payouts dispatched for total of {$totalDispatchedAmount} ₽.");

        return self::SUCCESS;
    }
}
