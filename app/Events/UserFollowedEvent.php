<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserFollowedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public User $idol, public User $follower) {}
}
