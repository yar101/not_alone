<?php

namespace App\Notifications;

use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\ContentPack;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ContentPackChangeApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly ContentPack $pack,
        public readonly array $approvedFields,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'content_pack_change_approved',
            'pack_id'         => $this->pack->id,
            'pack_title'      => $this->pack->title,
            'approved_fields' => $this->approvedFields,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.content_pack_change_approved');
    }

    protected function webPushBody(): string
    {
        $fieldNames = array_map(fn ($f) => match ($f) {
            'title'       => __('pack.field.title'),
            'description' => __('pack.field.description'),
            'price'       => __('pack.field.price'),
            default       => $f,
        }, $this->approvedFields);

        return __('push.content_pack_change_approved', [
            'title'  => $this->pack->title,
            'fields' => implode(', ', $fieldNames),
        ]);
    }
}
