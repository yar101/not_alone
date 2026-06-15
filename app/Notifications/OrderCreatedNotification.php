<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class OrderCreatedNotification extends Notification
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
            'type'         => 'order_created',
            'order_id'     => $this->order->id,
            'customer_id'  => $this->order->customer_id,
            'customer_name'=> $this->order->customer->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.order_created');
    }

    protected function webPushBody(): string
    {
        return __('push.order_created', ['name' => $this->order->customer->name]);
    }

    protected function webPushUrl(): string
    {
        return '/orders';
    }
}
