<?php

namespace App\Console\Commands;

use App\Contracts\MandarinGatewayInterface;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSelfEmployedReceiptsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'self-employed:monitor-receipts';

    /**
     * The console command description.
     */
    protected $description = 'Monitor self-employed receipts in FNS to detect cancelled receipts by idols';

    public function handle(MandarinGatewayInterface $gateway): int
    {
        $this->info('Monitoring FNS self-employed receipts for closed orders...');

        $orders = Order::where('status', OrderStatus::Closed)
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('payout_released_at')
            ->with('idol')
            ->get();

        $verifiedCount = 0;
        $cancelledAlerts = 0;

        foreach ($orders as $order) {
            $idol = $order->idol;
            if (! $idol || empty($idol->inn)) {
                continue;
            }

            try {
                // Check idol FNS tax status
                $status = $gateway->checkSelfEmployedStatus($idol->inn);
                if (! ($status['is_self_employed'] ?? false)) {
                    Log::warning("Idol #{$idol->id} (INN: {$idol->inn}) lost self-employed status in FNS!");
                    $this->warn("  ! Idol #{$idol->id} is no longer recognized as self-employed in FNS.");
                    $cancelledAlerts++;
                } else {
                    $verifiedCount++;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to check FNS status for idol #{$idol->id}: ".$e->getMessage());
            }
        }

        $this->info("FNS monitoring complete: verified {$verifiedCount} orders, detected {$cancelledAlerts} alerts.");

        return self::SUCCESS;
    }
}
