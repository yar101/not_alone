<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Order;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class OrderAcceptedNotification extends Notification implements ShouldQueue
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
            'type'       => 'order_accepted',
            'order_id'   => $this->order->id,
            'idol_id'    => $this->order->idol_id,
            'idol_name'  => $this->order->idol->name,
            'idol_gender' => $this->order->idol->gender,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.order_accepted');
    }

    protected function webPushBody(): string
    {
        return __('push.order_accepted', ['name' => $this->order->idol->name]);
    }

    protected function webPushUrl(): string
    {
        return '/orders';
    }
}
