<?php

namespace App\Notifications;

use App\Mail\IdolApprovedMail;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class IdolApprovedNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'idol_approved',
        ];
    }

    public function toMail(object $notifiable): IdolApprovedMail
    {
        return new IdolApprovedMail($notifiable);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.idol_approved');
    }

    protected function webPushBody(): string
    {
        return __('push.idol_approved');
    }
}
