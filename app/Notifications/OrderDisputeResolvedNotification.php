<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class OrderDisputeResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly Order $order,
        public readonly string $decision,
        public readonly ?string $note = null
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'order_dispute_resolved',
            'order_id' => $this->order->id,
            'decision' => $this->decision,
            'note' => $this->note,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return 'Спор по заказу рассмотрен';
    }

    protected function webPushBody(): string
    {
        return $this->decision === 'approved'
            ? "Спор по заказу #{$this->order->id} удовлетворён, средства возвращены."
            : "Спор по заказу #{$this->order->id} отклонён администратором.";
    }

    protected function webPushUrl(): string
    {
        return url("/orders?id={$this->order->id}");
    }
}
