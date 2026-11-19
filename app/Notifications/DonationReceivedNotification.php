<?php

namespace App\Notifications;

use App\Models\Donation;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class DonationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly Donation $donation
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $donorName = $this->donation->donor?->name ?? 'Пользователь';

        return [
            'type' => 'donation_received',
            'donation_id' => $this->donation->id,
            'donor_id' => $this->donation->donor_id,
            'donor_name' => $donorName,
            'amount' => (float) $this->donation->amount,
            'message' => "Вы получили донат {$this->donation->amount} от {$donorName}!".($this->donation->message ? " «{$this->donation->message}»" : ''),
            'donation_message' => $this->donation->message,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return 'Получен новый донат!';
    }

    protected function webPushBody(): string
    {
        $donorName = $this->donation->donor?->name ?? 'Пользователь';

        return "Вы получили донат {$this->donation->amount} от {$donorName}.".($this->donation->message ? " «{$this->donation->message}»" : '');
    }

    protected function webPushUrl(): string
    {
        return "/users/{$this->donation->idol_id}#donations";
    }
}
