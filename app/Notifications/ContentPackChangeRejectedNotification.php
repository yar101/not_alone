<?php

namespace App\Notifications;

use App\Models\ContentPack;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class ContentPackChangeRejectedNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(
        public readonly ContentPack $pack,
        public readonly ?string $adminComment = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'content_pack_change_rejected',
            'pack_id'       => $this->pack->id,
            'pack_title'    => $this->pack->title,
            'admin_comment' => $this->adminComment,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.content_pack_change_rejected');
    }

    protected function webPushBody(): string
    {
        return __('push.content_pack_change_rejected', ['title' => $this->pack->title]);
    }
}
