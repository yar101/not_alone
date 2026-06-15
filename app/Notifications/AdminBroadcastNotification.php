<?php

namespace App\Notifications;

use App\Models\AdminBroadcast;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class AdminBroadcastNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(private AdminBroadcast $broadcast) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        $locale = $notifiable->locale ?? config('app.locale');
        return [
            'type'          => 'admin_broadcast',
            'broadcast_id'  => $this->broadcast->id,
            'title_raw'     => $this->broadcast->getTranslation('title', $locale),
            'message_raw'   => $this->broadcast->getTranslation('body', $locale),
            'title_locales' => $this->broadcast->getTranslations('title'),
            'body_locales'  => $this->broadcast->getTranslations('body'),
        ];
    }

    protected function webPushTitle(): string
    {
        $locale = $this->notifiable->locale ?? config('app.locale');
        return $this->broadcast->getTranslation('title', $locale);
    }

    protected function webPushBody(): string
    {
        $locale = $this->notifiable->locale ?? config('app.locale');
        return __('push.admin_broadcast', ['message' => $this->broadcast->getTranslation('body', $locale)], $locale);
    }
}
