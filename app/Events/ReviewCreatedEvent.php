<?php

namespace App\Events;

use App\Models\Review;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReviewCreatedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Review $review) {}
}
