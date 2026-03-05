<?php

namespace App\Notifications;

use App\Models\AdminBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AdminBroadcastNotification extends Notification
{
    use Queueable;

    public function __construct(private AdminBroadcast $broadcast) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type'         => 'admin_broadcast',
            'broadcast_id' => $this->broadcast->id,
            'title'        => $this->broadcast->title,
            'message'      => $this->broadcast->body,
        ];
    }
}
