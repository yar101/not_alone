<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Services\OrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompleteOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly Order $order) {}

    /**
     * Execute the job.
     */
    public function handle(OrderService $service): void
    {
        $this->order->refresh();

        if ($this->order->status !== OrderStatus::Paid) {
            return;
        }

        if (! $this->order->paid_at) {
            return;
        }

        $delayHours = (float) PlatformSetting::get('order_auto_complete_delay', 72);
        $deadline = $this->order->paid_at->copy()->addSeconds((int) ($delayHours * 3600));

        if (now()->lt($deadline)) {
            // Re-queue with the remaining time
            $remainingSeconds = now()->diffInSeconds($deadline, false);
            if ($remainingSeconds > 0) {
                $this->release($remainingSeconds);
                return;
            }
        }

        $service->autoCompleteOrder($this->order);
    }
}
