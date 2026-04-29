<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
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
        // Re-fetch or refresh to be sure we have the latest status
        $this->order->refresh();

        if ($this->order->status === OrderStatus::Paid) {
            $service->autoCompleteOrder($this->order);
        }
    }
}
