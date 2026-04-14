<?php

namespace App\Notifications;

use App\Models\ContentPack;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContentPackChangeApprovedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ContentPack $pack,
        public readonly array $approvedFields,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $fieldNames = array_map(fn ($f) => match ($f) {
            'title'       => 'название',
            'description' => 'описание',
            'price'       => 'цена',
            default       => $f,
        }, $this->approvedFields);

        $fieldsStr = implode(', ', $fieldNames);

        return [
            'type'    => 'content_pack_change_approved',
            'pack_id' => $this->pack->id,
            'message' => 'Изменения в паке «' . $this->pack->title . '» одобрены: ' . $fieldsStr . '.',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
