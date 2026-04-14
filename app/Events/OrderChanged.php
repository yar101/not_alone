<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $userId,
        public array  $order,
        public string $changeType,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('orders.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'order.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'change_type' => $this->changeType,
            'order'       => $this->order,
        ];
    }
}
