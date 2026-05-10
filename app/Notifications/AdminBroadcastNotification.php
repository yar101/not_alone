<?php

namespace App\Notifications;

use App\Models\AdminBroadcast;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class AdminBroadcastNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(private AdminBroadcast $broadcast) {}

    public function via($notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'         => 'admin_broadcast',
            'broadcast_id' => $this->broadcast->id,
            'title_raw'    => $this->broadcast->title,
            'message_raw'  => $this->broadcast->body,
        ];
    }

    protected function webPushTitle(): string
    {
        return $this->broadcast->title;
    }

    protected function webPushBody(): string
    {
        return __('push.admin_broadcast', ['message' => $this->broadcast->body]);
    }
}
