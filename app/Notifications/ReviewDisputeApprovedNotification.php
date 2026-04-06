<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReviewDisputeApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(private ?string $adminNote = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = 'Ваша жалоба на отзыв одобрена — отзыв скрыт.';
        if ($this->adminNote) {
            $message .= ' Комментарий администратора: ' . $this->adminNote;
        }

        return [
            'type'    => 'review_dispute_approved',
            'title'   => 'Жалоба на отзыв одобрена',
            'message' => $message,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
