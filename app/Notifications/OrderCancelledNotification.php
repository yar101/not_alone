<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Order;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class OrderCancelledNotification extends Notification implements ShouldQueue
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
            'type'          => 'order_cancelled',
            'order_id'      => $this->order->id,
            'cancelled_by'  => $this->order->cancelled_by,
            'cancel_reason' => $this->order->cancel_reason,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.order_cancelled');
    }

    protected function webPushBody(): string
    {
        return __('push.order_cancelled');
    }

    protected function webPushUrl(): string
    {
        return '/orders';
    }
}
