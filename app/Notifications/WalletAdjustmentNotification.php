<?php

namespace App\Notifications;

use App\Models\WalletTransaction;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class WalletAdjustmentNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly WalletTransaction $transaction
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $amount = (float) $this->transaction->amount;
        $isCredit = $amount > 0;

        return [
            'type' => 'wallet_adjustment',
            'transaction_id' => $this->transaction->id,
            'amount' => abs($amount),
            'is_credit' => $isCredit,
            'tx_type' => $this->transaction->type->value,
            'description' => $this->transaction->description,
            'balance_after' => (float) $this->transaction->balance_after,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        $amount = (float) $this->transaction->amount;

        return $amount > 0
            ? __('push.title.wallet_credit')
            : __('push.title.wallet_debit');
    }

    protected function webPushBody(): string
    {
        $amount = (float) $this->transaction->amount;
        $formatted = ($amount > 0 ? '+' : '-').number_format(abs($amount), 2, '.', ' ').' ₽';

        return __('push.wallet_adjustment', [
            'amount' => $formatted,
            'description' => $this->transaction->description,
        ]);
    }

    protected function webPushUrl(): string
    {
        return '/wallet';
    }
}
