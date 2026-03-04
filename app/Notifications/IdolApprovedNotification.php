<?php

namespace App\Notifications;

use App\Mail\IdolApprovedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class IdolApprovedNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'idol_approved',
            'message' => 'Ваша заявка одобрена! Вы стали Айдолом 🎉',
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
}
