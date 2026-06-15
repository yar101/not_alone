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

    public function __construct(public readonly string $message = 'Push-уведомления работают корректно!') {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'test',
            'message_raw' => $this->message,
        ];
    }

    protected function webPushTitle(): string
    {
        return __('push.title.test');
    }

    protected function webPushBody(): string
    {
        return __('push.test', ['message' => $this->message]);
    }
}
