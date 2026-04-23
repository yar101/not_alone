<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class TestNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly string $message = 'Тестовое уведомление') {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'test',
            'message' => $this->message,
        ];
    }

    protected function webPushBody(): string
    {
        return __('push.test', ['message' => $this->message]);
    }
}
