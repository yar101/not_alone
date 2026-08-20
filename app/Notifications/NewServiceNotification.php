<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class NewServiceNotification extends Notification implements ShouldQueue
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
            'type' => 'new_service',
            'service_id' => $this->service->id,
            'service_name' => $this->service->getTranslation('name', 'ru'),
            'user_id' => $this->service->user_id,
            'user_name' => $this->service->user->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.new_service') ?: 'Новая услуга';
    }

    protected function webPushBody(): string
    {
        return __('push.new_service', ['name' => $this->service->user->name, 'title' => $this->service->getTranslation('name', 'ru')])
            ?: "{$this->service->user->name} добавил(а) новую услугу: {$this->service->getTranslation('name', 'ru')}";
    }

    protected function webPushUrl(): string
    {
        return "/users/{$this->service->user_id}";
    }
}
