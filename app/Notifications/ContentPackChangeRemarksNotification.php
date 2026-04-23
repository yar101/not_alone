<?php

namespace App\Notifications;

use App\Models\ContentPack;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ContentPackChangeRemarksNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly ContentPack $pack,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'content_pack_change_remarks',
            'pack_id' => $this->pack->id,
            'message' => 'По изменениям в паке «' . $this->pack->title . '» есть замечания — проверьте и исправьте.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushBody(): string
    {
        return __('push.content_pack_change_remarks', ['title' => $this->pack->title]);
    }
}
