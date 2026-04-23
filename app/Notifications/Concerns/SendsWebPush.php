<?php

namespace App\Notifications\Concerns;

use NotificationChannels\WebPush\WebPushMessage;

trait SendsWebPush
{
    abstract protected function webPushBody(): string;

    protected function webPushUrl(): string
    {
        return '/notifications';
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('NoAlone')
            ->body($this->webPushBody())
            ->icon('/pwa-192x192.png')
            ->badge('/pwa-64x64.png')
            ->data(['url' => $this->webPushUrl()]);
    }
}
