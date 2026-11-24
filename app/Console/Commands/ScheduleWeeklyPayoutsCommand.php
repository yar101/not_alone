<?php

namespace App\Console\Commands;

use App\Contracts\MandarinGatewayInterface;
use App\Services\PayoutManagementService;
use Illuminate\Console\Command;
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

    public function handle(MandarinGatewayInterface $gateway, PayoutManagementService $payoutService): int
    {
        $this->info('Starting weekly SBP payouts process for self-employed idols...');

        $isForce = (bool) $this->option('force');
        $autoEnabled = $payoutService->getAutoPayoutEnabled();

        if (! $autoEnabled && ! $isForce) {
            $this->warn('Automated weekly payouts are currently DISABLED in platform settings.');
            $this->info('Generating draft payout batch for admin review...');

            $batch = $payoutService->createPendingBatch(isAutomated: false, force: false);

            $this->line("  ✓ Draft Batch #{$batch->batch_number} created with {$batch->total_recipients} recipients for a total of {$batch->total_amount} ₽.");
            $this->info('Batch is awaiting manual confirmation by administrator in Admin Panel -> Payouts.');

            Log::info("ScheduleWeeklyPayouts: Draft batch #{$batch->batch_number} generated for manual approval ({$batch->total_amount} ₽).");

            return self::SUCCESS;
        }

        // Auto payouts are enabled or forced
        $this->info('Automated payouts enabled. Compiling and dispatching batch...');
        $batch = $payoutService->createPendingBatch(isAutomated: true, force: $isForce);

        if ($batch->total_recipients === 0 || $batch->total_amount <= 0) {
            $this->info('No eligible idols found for weekly payout.');
            $batch->update(['status' => 'completed']);

            return self::SUCCESS;
        }

        $this->info("Dispatching batch #{$batch->batch_number} ({$batch->total_recipients} recipients, total: {$batch->total_amount} ₽)...");

        $executedBatch = $payoutService->dispatchBatch($batch, admin: null);

        $this->info("Batch execution finished. Status: {$executedBatch->status} (Success: {$executedBatch->successful_count}, Failed: {$executedBatch->failed_count}).");

        Log::info("ScheduleWeeklyPayouts: Automated batch #{$batch->batch_number} executed. Status: {$executedBatch->status}.");

        return self::SUCCESS;
    }
}
