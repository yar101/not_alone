<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Throwable;

trait SafeBroadcast
{
    /**
     * Safely broadcast an event without crashing the HTTP request or queue job if broadcasting fails.
     */
    protected function safeBroadcast(mixed $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('Broadcast failed: '.$e->getMessage(), [
                'event' => is_object($event) ? get_class($event) : gettype($event),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
