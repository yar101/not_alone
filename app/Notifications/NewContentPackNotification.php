<?php

namespace App\Notifications;

use App\Models\ContentPack;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class NewContentPackNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly ContentPack $pack) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'new_content_pack',
            'pack_id' => $this->pack->id,
            'pack_title' => $this->pack->title,
            'user_id' => $this->pack->user_id,
            'user_name' => $this->pack->user->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.new_content_pack') ?: 'Новый контент-пак';
    }

    protected function webPushBody(): string
    {
        return __('push.new_content_pack', ['name' => $this->pack->user->name, 'title' => $this->pack->title])
            ?: "{$this->pack->user->name} опубликовал(а) новый пак «{$this->pack->title}»";
    }

    protected function webPushUrl(): string
    {
        return "/users/{$this->pack->user_id}";
    }
}
