<?php

namespace App\Events;

use App\Models\ContentPackPurchase;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContentPackPurchasedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public ContentPackPurchase $purchase) {}
}
