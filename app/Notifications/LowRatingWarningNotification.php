<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class LowRatingWarningNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(private int $threshold) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'low_rating_warning',
            'threshold' => $this->threshold,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.low_rating_warning');
    }

    protected function webPushBody(): string
    {
        return __('push.low_rating_warning', ['threshold' => $this->threshold]);
    }
}
