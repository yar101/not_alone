<?php

namespace App\Notifications;

use App\Mail\IdolRejectedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class IdolRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $reason
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
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
}
