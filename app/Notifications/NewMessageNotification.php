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
        $senderName = $this->getSenderName();
        $event = $this->message->metadata['event'] ?? null;

        if ($this->message->type === 'system' && in_array($event, ['chat_opened', 'chat_closed'])) {
            return [
                'type'            => 'new_message',
                'message_id'      => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_id'       => null,
                'sender_name'     => __('chat.support'),
                'message'         => __("notification.msg.$event"),
                'title'           => __('notification.type.chat_status'),
            ];
        }

        return [
            'type'            => 'new_message',
            'message_id'      => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $senderName,
            'message'         => __('notification.msg.new_message', ['name' => $senderName]),
            'title'           => __('notification.type.new_message'),
        ];
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
        return __('push.title.new_message');
    }

    protected function webPushBody(): string
    {
        $event = $this->message->metadata['event'] ?? null;
        if ($this->message->type === 'system' && in_array($event, ['chat_opened', 'chat_closed'])) {
            return __("notification.msg.$event");
        }
        return __('push.new_message', ['name' => $this->getSenderName()]);
    }

    protected function webPushUrl(): string
    {
        return "/chat/{$this->message->conversation_id}";
    }

    private function getSenderName(): string
    {
        if ($this->message->type === 'support') {
            return __('chat.support');
        }

        return $this->message->sender?->name ?? __('nav.user');
    }
}
