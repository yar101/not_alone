<?php

namespace App\Notifications;

use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class AdminRatingNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        private int $delta,
        private int $newRating,
        private ?string $note = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'admin_rating',
            'delta' => $this->delta,
            'new_rating' => $this->newRating,
            'note' => $this->note,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.admin_rating');
    }

    protected function webPushBody(): string
    {
        $sign = $this->delta > 0 ? '+' : '';

        return __('push.admin_rating', [
            'delta' => $sign.$this->delta,
            'rating' => $this->newRating,
        ]);
    }
}
