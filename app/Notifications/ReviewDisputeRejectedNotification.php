<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewDisputeRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(private ?string $adminNote = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = 'Ваша жалоба на отзыв отклонена.';
        if ($this->adminNote) {
            $message .= ' Комментарий администратора: ' . $this->adminNote;
        }

        return [
            'type'    => 'review_dispute_rejected',
            'title'   => 'Жалоба на отзыв отклонена',
            'message' => $message,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
