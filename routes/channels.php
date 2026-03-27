<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    return $user->conversationParticipants()
        ->where('conversation_id', $conversationId)
        ->exists();
});

Broadcast::channel('presence-conversation.{conversationId}', function ($user, $conversationId) {
    if ($user->conversationParticipants()->where('conversation_id', $conversationId)->exists()) {
        return ['id' => $user->id, 'name' => $user->name, 'avatar_url' => $user->avatar_url];
    }
    return false;
});

Broadcast::channel('presence-online', function ($user) {
    return ['id' => $user->id, 'name' => $user->name];
});

Broadcast::channel('orders.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});
