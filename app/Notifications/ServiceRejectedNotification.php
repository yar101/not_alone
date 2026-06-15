<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ServiceRejectedNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly Service $service) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'             => 'service_rejected',
            'service_id'       => $this->service->id,
            'service_name'     => $this->service->name,
            'rejection_reason' => $this->service->rejection_reason,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.service_rejected');
    }

    protected function webPushBody(): string
    {
        return __('push.service_rejected', ['name' => $this->service->name]);
    }
}
