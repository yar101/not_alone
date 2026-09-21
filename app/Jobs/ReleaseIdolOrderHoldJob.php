<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Services\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ReleaseIdolOrderHoldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Delete the job if its models no longer exist.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Order $order) {}

    public function handle(WalletService $walletService): void
    {
        $this->order->refresh();

        if ($this->order->status !== OrderStatus::Completed) {
            return;
        }

        if ($this->order->payout_released_at !== null) {
            return;
        }

        if (! $this->order->completed_at) {
            return;
        }

        // Check if there is an open dispute
        if ($this->order->disputes()->where('status', 'open')->exists()) {
            return;
        }

        $windowMinutes = (int) PlatformSetting::get('order_dispute_window_minutes', 60);
        $deadline = $this->order->completed_at->copy()->addMinutes($windowMinutes);

        if (now()->lt($deadline)) {
            $remaining = now()->diffInSeconds($deadline, false);
            if ($remaining > 0) {
                $this->release($remaining);

                return;
            }
        }

        $walletService->releaseIdolPayout($this->order);
    }
}
