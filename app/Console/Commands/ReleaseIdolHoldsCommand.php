<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Services\WalletService;
use Illuminate\Console\Command;

class ReleaseIdolHoldsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:release-idol-holds';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Release completed order holds to idol available balance after dispute window';

    /**
     * Execute the console command.
     */
    public function handle(WalletService $walletService): int
    {
        $windowMinutes = max(1, (int) PlatformSetting::get('order_dispute_window_minutes', 60));
        $cutoff = now()->subMinutes($windowMinutes);

        $orders = Order::where('status', OrderStatus::Completed)
            ->whereNull('payout_released_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->whereDoesntHave('disputes', function ($q) {
                $q->where('status', 'open');
            })
            ->lazyById(100);

        $count = 0;
        foreach ($orders as $order) {
            try {
                $released = $walletService->releaseIdolPayout($order);
                if ($released) {
                    $count++;
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Failed to release idol hold for order #{$order->id}: {$e->getMessage()}");
            }
        }

        $this->info("Released {$count} idol hold payouts.");

        return self::SUCCESS;
    }
}
