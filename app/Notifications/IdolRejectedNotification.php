<?php

namespace App\Notifications;

use App\Mail\IdolRejectedMail;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class IdolRejectedNotification extends Notification
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
            'message' => 'Ваша заявка на статус Айдола отклонена.',
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

    protected function webPushBody(): string
    {
        return __('push.idol_rejected');
    }
}
