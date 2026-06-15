<?php

namespace App\Notifications;

use App\Models\Post;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class NewPostNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly Post $post) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'      => 'new_post',
            'post_id'   => $this->post->id,
            'user_id'   => $this->post->user_id,
            'user_name' => $this->post->user->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        return __('push.title.new_post') ?: 'Новая публикация';
    }

    protected function webPushBody(): string
    {
        return __('push.new_post', ['name' => $this->post->user->name]) ?: "{$this->post->user->name} опубликовал(а) новую запись";
    }

    protected function webPushUrl(): string
    {
        return "/users/{$this->post->user_id}";
    }
}
