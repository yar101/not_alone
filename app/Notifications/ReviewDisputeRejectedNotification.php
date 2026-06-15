<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ReviewDisputeRejectedNotification extends Notification
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
        return [
            'type'       => 'review_dispute_rejected',
            'admin_note' => $this->adminNote,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.review_dispute_rejected');
    }

    protected function webPushBody(): string
    {
        return __('push.review_dispute_rejected');
    }
}
