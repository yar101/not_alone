<?php

namespace App\Notifications;

use App\Models\ContentPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContentPackRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly ContentPack $pack) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'    => 'content_pack_rejected',
            'pack_id' => $this->pack->id,
            'message' => 'Ваш пак «' . $this->pack->title . '» был отклонён администрацией.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
