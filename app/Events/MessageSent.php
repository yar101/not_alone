<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message)
    {
        $this->message->loadMissing('conversation');
    }

    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('conversation.' . $this->message->conversation_id)];

        // Also broadcast on admin channel for support conversations
        if ($this->message->conversation?->is_support) {
            $channels[] = new PrivateChannel('admin.support.' . $this->message->conversation_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $sender = $this->message->sender;
        return [
            'id'              => $this->message->id,
            'body'            => $this->message->body,
            'type'            => $this->message->type ?? 'user',
            'metadata'        => $this->message->metadata,
            'sender_id'       => $this->message->sender_id,
            'sender_name'     => $sender?->name,
            'sender_avatar'   => $sender?->avatar_url,
            'created_at'      => $this->message->created_at->toISOString(),
            'conversation_id' => $this->message->conversation_id,
        ];
    }
}
