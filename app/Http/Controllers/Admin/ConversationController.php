<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $query = $conversation->messages()->with('sender')->latest();

        if ($request->before_id) {
            $query->where('id', '<', $request->before_id);
        }

        $messages = $query->take(50)->get()->reverse()->values();

        $hasMore = $messages->isNotEmpty() &&
            $conversation->messages()->where('id', '<', $messages->first()->id)->exists();

        $mapped = $messages->map(fn($m) => [
            'id'            => $m->id,
            'body'          => $m->body,
            'type'          => $m->type ?? 'user',
            'metadata'      => $m->metadata,
            'sender_id'     => $m->sender_id,
            'sender_name'   => $m->sender?->name,
            'sender_avatar' => $m->sender?->avatar_url,
            'created_at'    => $m->created_at->toISOString(),
        ]);

        return response()->json(['messages' => $mapped, 'has_more' => $hasMore]);
    }
}
