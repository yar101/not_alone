<?php

namespace App\Notifications;

use App\Mail\IdolRejectedMail;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class IdolRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        private readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'idol_rejected',
            'reason' => $this->reason,
        ];
    }

    public function toMail(object $notifiable): IdolRejectedMail
    {
        return new IdolRejectedMail($notifiable, $this->reason);
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.idol_rejected');
    }

    protected function webPushBody(): string
    {
        return __('push.idol_rejected');
    }
}
