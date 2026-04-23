<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ReviewDisputeApprovedNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(private ?string $adminNote = null) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
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

    protected function webPushBody(): string
    {
        return __('push.review_dispute_approved');
    }
}
