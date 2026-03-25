<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LowRatingWarningNotification extends Notification
{
    use Queueable;

    public function __construct(private int $threshold) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'low_rating_warning',
            'title'   => 'Рейтинг упал ниже порога',
            'message' => 'Ваш рейтинг упал ниже ' . $this->threshold . '. Рекомендуем снизить цены на услуги.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
