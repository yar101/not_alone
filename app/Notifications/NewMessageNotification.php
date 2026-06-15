<?php

namespace App\Notifications;

use App\Models\Message;
use App\Notifications\Concerns\SendsWebPush;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

class NewMessageNotification extends Notification
{
    use Queueable;
    use SendsWebPush;

    public function __construct(public readonly Message $message) {}

    public function via(object $notifiable): array
    {
        return ['database', WebPushChannel::class];
    }

    public function toDatabase(object $notifiable): array
    {
        $event      = $this->message->metadata['event'] ?? null;
        $senderName = $this->getSenderName();

        $data = [
            'type'            => 'new_message',
            'message_id'      => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $senderName,
            'message_type'    => $this->message->type,
            'event'           => $event,
        ];

        return $data;
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    protected function webPushTitle(): string
    {
        $event = $this->message->metadata['event'] ?? null;
        if ($this->message->type === 'system' && in_array($event, ['chat_opened', 'chat_closed'])) {
            return __('notification.type.chat_status');
        }
        if ($this->message->type === 'support') {
            return __('notification.type.support_message');
        }
        return __('push.title.new_message');
    }

    protected function webPushBody(): string
    {
        $event = $this->message->metadata['event'] ?? null;
        if ($this->message->type === 'system' && in_array($event, ['chat_opened', 'chat_closed'])) {
            return __("notification.msg.$event");
        }
        $name = $this->message->type === 'support' ? 'NOT ALONE' : $this->getSenderName();
        return __('push.new_message', ['name' => $name]);
    }

    protected function webPushUrl(): string
    {
        return "/chat/{$this->message->conversation_id}";
    }

    private function getSenderName(): string
    {
        if ($this->message->type === 'support') {
            return 'NOT ALONE';
        }

        return $this->message->sender?->name ?? __('nav.user');
    }
}
