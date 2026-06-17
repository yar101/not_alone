<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushMessage;

trait SendsWebPush
{
    protected object $notifiable;

    abstract protected function webPushBody(): string;

    protected function webPushTitle(): string
    {
        return 'Not Alone';
    }

    protected function webPushUrl(): string
    {
        return '/notifications';
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        $this->notifiable = $notifiable;

        $oldLocale = app()->getLocale();
        $targetLocale = $notifiable->locale ?? $oldLocale;
        
        if ($targetLocale !== $oldLocale) {
            app()->setLocale($targetLocale);
        }

        try {
            return (new WebPushMessage)
                ->title($this->webPushTitle())
                ->body($this->webPushBody())
                ->icon('/pwa-192x192-v2.png')
                ->badge('/pwa-64x64-v2.png')
                ->data(['url' => $this->webPushUrl()]);
        } finally {
            if ($targetLocale !== $oldLocale) {
                app()->setLocale($oldLocale);
            }
        }
    }
}
