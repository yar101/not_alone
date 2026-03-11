<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminRatingNotification extends Notification
{
    use Queueable;

    public function __construct(
        private int $delta,
        private int $newRating,
        private ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $sign    = $this->delta > 0 ? '+' : '';
        $message = 'Администратор изменил ваш рейтинг на ' . $sign . $this->delta . '. Текущий рейтинг: ' . $this->newRating . '.';

        if ($this->note) {
            $message .= ' Причина: ' . $this->note;
        }

        return [
            'type'       => 'admin_rating',
            'message'    => $message,
            'delta'      => $this->delta,
            'new_rating' => $this->newRating,
            'note'       => $this->note,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
