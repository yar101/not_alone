<?php

namespace App\Notifications;

use App\Models\ContentPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContentPackRemarksNotification extends Notification
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
            'type'    => 'content_pack_remarks',
            'pack_id' => $this->pack->id,
            'message' => 'По вашему паку «' . $this->pack->title . '» есть замечания от администрации.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
