<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Services\OrderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCompleteOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:auto-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Auto-complete overdue paid orders where delay threshold has passed';

    /**
     * Execute the console command.
     */
    public function handle(OrderService $orderService): int
    {
        $delayHours = max(0.01, (float) PlatformSetting::get('order_auto_complete_delay', 72));
        $cutoff = now()->subSeconds((int) ($delayHours * 3600));

        $orders = Order::where('status', OrderStatus::Paid)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $cutoff)
            ->lazyById(100);

        $count = 0;
        foreach ($orders as $order) {
            try {
                $orderService->autoCompleteOrder($order);
                $count++;
            } catch (\Throwable $e) {
                Log::error("Failed to auto-complete order #{$order->id}: {$e->getMessage()}");
            }
        }

        $this->info("Auto-completed {$count} overdue orders.");

        return self::SUCCESS;
    }
}
