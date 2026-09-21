<?php

namespace App\Notifications;

use App\Models\Order;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class IdolPayoutReleasedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly Order $order,
        public readonly float $amount
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'idol_payout_released',
            'order_id' => $this->order->id,
            'amount' => $this->amount,
            'message' => "Оплата за заказ #{$this->order->id} на сумму {$this->amount} ₽ разблокирована и доступна к выводу.",
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return 'Средства разблокированы';
    }

    protected function webPushBody(): string
    {
        return "Оплата за заказ #{$this->order->id} ({$this->amount} ₽) поступила на доступный баланс.";
    }

    protected function webPushUrl(): string
    {
        return '/wallet';
    }
}
