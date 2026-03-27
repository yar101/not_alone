<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $conversationId,
        public int $orderId,
        public string $status,
        public ?int $cancelledBy = null,
        public ?string $cancelledByName = null,
        public ?string $cancelReason = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('conversation.' . $this->conversationId)];
    }

    public function broadcastAs(): string
    {
        return 'order.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'          => $this->orderId,
            'status'            => $this->status,
            'cancelled_by'      => $this->cancelledBy,
            'cancelled_by_name' => $this->cancelledByName,
            'cancel_reason'     => $this->cancelReason,
        ];
    }
}
