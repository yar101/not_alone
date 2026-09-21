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
        $windowMinutes = (int) PlatformSetting::get('order_dispute_window_minutes', 60);
        $cutoff = now()->subMinutes($windowMinutes);

        $orders = Order::where('status', OrderStatus::Completed)
            ->whereNull('payout_released_at')
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->whereDoesntHave('disputes', function ($q) {
                $q->where('status', 'open');
            })
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            $released = $walletService->releaseIdolPayout($order);
            if ($released) {
                $count++;
            }
        }

        $this->info("Released {$count} idol hold payouts.");

        return self::SUCCESS;
    }
}
