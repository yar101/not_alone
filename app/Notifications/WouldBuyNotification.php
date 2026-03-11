<?php

namespace App\Notifications;

use App\Models\Service;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class WouldBuyNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Service $service,
        private User $fromUser,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'would_buy',
            'message'    => $this->fromUser->name . ' купил бы услугу «' . $this->service->name . '», если бы она была дешевле',
            'service_id' => $this->service->id,
            'from_user'  => [
                'id'         => $this->fromUser->id,
                'name'       => $this->fromUser->name,
                'avatar_url' => $this->fromUser->avatar_url,
            ],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
