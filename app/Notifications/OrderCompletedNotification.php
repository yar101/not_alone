<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class OrderCompletedNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly Order $order) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'order_completed',
            'order_id' => $this->order->id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushBody(): string
    {
        return __('push.order_completed');
    }

    protected function webPushUrl(): string
    {
        return '/orders';
    }
}
