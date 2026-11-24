<?php

namespace App\Console\Commands;

use App\Contracts\MandarinGatewayInterface;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcilePayoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'payouts:reconcile {--force : Reconcile all pending payouts immediately}';

    /**
     * The console command description.
     */
    protected $description = 'Reconcile pending SBP payouts against Mandarin BaaS clearing operations';

    public function handle(MandarinGatewayInterface $gateway): int
    {
        $this->info('Reconciling pending SBP payouts...');

        $query = WalletTransaction::where('type', WalletTransactionType::Withdrawal)
            ->where('status', WalletTransactionStatus::PendingBank);

        if (! $this->option('force')) {
            $query->where('created_at', '<=', now()->subMinutes(2));
        }

        $pendingTxs = $query->get();

        if ($pendingTxs->isEmpty()) {
            $this->info('No pending bank payouts found to reconcile.');

            return self::SUCCESS;
        }

        $reconciledCount = 0;

        foreach ($pendingTxs as $tx) {
            $meta = $tx->metadata ?? [];
            $gatewayOpId = $meta['gateway_operation_id'] ?? null;

            if (! $gatewayOpId) {
                continue;
            }

            try {
                $statusRes = $gateway->getOperationStatus($gatewayOpId);
                $gwStatus = $statusRes['status'] ?? 'pending_bank';

                if (in_array($gwStatus, ['success', 'settled'])) {
                    DB::transaction(function () use ($tx, $statusRes, $meta) {
                        $lockedTx = WalletTransaction::where('id', $tx->id)->lockForUpdate()->first();
                        $wallet = Wallet::where('id', $tx->wallet_id)->lockForUpdate()->first();

                        $payoutAmount = abs((float) $lockedTx->amount);
                        if ($wallet) {
                            $heldBefore = (float) $wallet->pink_held_balance;
                            $wallet->forceFill([
                                'pink_held_balance' => max(0.00, (float) bcsub((string) $heldBefore, (string) $payoutAmount, 2)),
                            ])->save();
                        }

                        $meta['payout_status'] = 'settled';
                        $meta['fns_receipt_url'] = $statusRes['fns_receipt_url'] ?? $meta['fns_receipt_url'] ?? null;
                        $meta['settled_at'] = now()->toISOString();

                        $lockedTx->update([
                            'status' => WalletTransactionStatus::Completed,
                            'metadata' => $meta,
                        ]);
                    });

                    $reconciledCount++;
                    $this->line("  ✓ Payout Tx #{$tx->id} settled successfully.");
                } elseif (in_array($gwStatus, ['failed', 'rejected'])) {
                    DB::transaction(function () use ($tx, $statusRes, $meta) {
                        $lockedTx = WalletTransaction::where('id', $tx->id)->lockForUpdate()->first();
                        $wallet = Wallet::where('id', $tx->wallet_id)->lockForUpdate()->first();

                        $payoutAmount = abs((float) $lockedTx->amount);
                        if ($wallet) {
                            $heldBefore = (float) $wallet->pink_held_balance;
                            $balBefore = (float) $wallet->pink_balance;

                            $heldAfter = max(0.00, (float) bcsub((string) $heldBefore, (string) $payoutAmount, 2));
                            $balAfter = (float) bcadd((string) $balBefore, (string) $payoutAmount, 2);

                            $wallet->forceFill([
                                'pink_balance' => $balAfter,
                                'pink_held_balance' => $heldAfter,
                            ])->save();
                        }

                        $meta['payout_status'] = 'failed';
                        $meta['failure_reason'] = $statusRes['failure_reason'] ?? 'Bank rejection during reconciliation';

                        $lockedTx->update([
                            'status' => WalletTransactionStatus::Failed,
                            'metadata' => $meta,
                        ]);
                    });

                    $reconciledCount++;
                    $this->warn("  ✗ Payout Tx #{$tx->id} rejected by bank; returned {$tx->amount} to pink balance.");
                }
            } catch (\Throwable $e) {
                Log::error("Failed to reconcile payout transaction #{$tx->id}: ".$e->getMessage());
                $this->error("  ✗ Tx #{$tx->id}: {$e->getMessage()}");
            }
        }

        $this->info("Reconciled {$reconciledCount} transactions.");

        return self::SUCCESS;
    }
}
