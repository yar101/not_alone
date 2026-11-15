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
        public ?float $pinkBalance = null,
        public ?float $pinkHeldBalance = null,
        public ?float $pinkTotalBalance = null,
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
        $payload = [
            'user_id' => $this->userId,
            'balance' => $this->balance,
            'held_balance' => $this->heldBalance,
            'total_balance' => $this->totalBalance,
        ];

        if ($this->pinkBalance !== null) {
            $payload['pink_balance'] = $this->pinkBalance;
            $payload['pink_held_balance'] = $this->pinkHeldBalance ?? 0.0;
            $payload['pink_total_balance'] = $this->pinkTotalBalance ?? 0.0;
        }

        return $payload;
    }
}
