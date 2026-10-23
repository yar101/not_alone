<?php

namespace App\Events;

use App\Models\ContentPack;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPackPublishedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ContentPack $pack) {}
}
