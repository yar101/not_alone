<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WalletBalanceUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Determine if the event should be broadcast after all database transactions are committed.
     */
    public bool $afterCommit = true;

    public function __construct(
        public int $userId,
        public float $balance,
        public float $heldBalance,
        public float $totalBalance,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'wallet.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'balance' => $this->balance,
            'held_balance' => $this->heldBalance,
            'total_balance' => $this->totalBalance,
        ];
    }
}
