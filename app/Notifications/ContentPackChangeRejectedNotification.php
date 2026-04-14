<?php

namespace App\Notifications;

use App\Models\ContentPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContentPackChangeRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ContentPack $pack,
        public readonly ?string $adminComment = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $message = 'Изменения в паке «' . $this->pack->title . '» отклонены.';
        if ($this->adminComment) {
            $message .= ' Комментарий: ' . $this->adminComment;
        }

        return [
            'type'    => 'content_pack_change_rejected',
            'pack_id' => $this->pack->id,
            'message' => $message,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
