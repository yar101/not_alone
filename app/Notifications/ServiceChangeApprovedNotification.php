<?php

namespace App\Notifications;

use App\Models\Service;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ServiceChangeApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly Service $service, public readonly array $fields, public readonly ?array $flaggedFields = null, public readonly ?array $fieldComments = null) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'service_change_approved',
            'service_id' => $this->service->id,
            'service_name' => $this->service->name,
            'fields' => $this->fields,
            'flagged_fields' => $this->flaggedFields,
            'field_comments' => $this->fieldComments,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.service_change_approved');
    }

    protected function webPushBody(): string
    {
        return __('push.service_change_approved', ['name' => $this->service->name]);
    }
}
