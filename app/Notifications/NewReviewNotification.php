<?php

namespace App\Notifications;

use App\Models\Review;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class NewReviewNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly Review $review,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $stars = str_repeat('★', $this->review->rating) . str_repeat('☆', 5 - $this->review->rating);

        return [
            'type'    => 'new_review',
            'message' => 'Вам оставили отзыв ' . $stars,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.new_review');
    }

    protected function webPushBody(): string
    {
        $stars = str_repeat('★', $this->review->rating) . str_repeat('☆', 5 - $this->review->rating);
        return __('push.new_review', ['stars' => $stars]);
    }
}
