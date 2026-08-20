<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $query = Conversation::query()
            ->with(['users', 'lastMessage'])
            ->latest('updated_at');

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        if ($request->filled('type')) {
            if ($request->type === 'order') {
                $query->whereNotNull('order_id');
            } elseif ($request->type === 'direct') {
                $query->whereNull('order_id')->where('is_support', false);
            } elseif ($request->type === 'support') {
                $query->where('is_support', true);
            }
        }

        if ($request->filled('user_search')) {
            $search = $request->user_search;
            $query->whereHas('users', function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->where('users.id', $search);
                } else {
                    $q->where(function ($sub) use ($search) {
                        $sub->where('users.name', 'like', '%'.$search.'%')
                            ->orWhere('users.email', 'like', '%'.$search.'%');
                    });
                }
            });
        }

        $conversations = $query->paginate(20)->withQueryString()->through(fn ($c) => [
            'id' => $c->id,
            'order_id' => $c->order_id,
            'is_support' => $c->is_support,
            'closed_at' => $c->closed_at?->toISOString(),
            'updated_at' => $c->updated_at->toISOString(),
            'users' => $c->users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar_url' => $u->avatar_url,
                'is_idol' => $u->is_idol,
            ]),
            'last_message' => $c->lastMessage ? [
                'id' => $c->lastMessage->id,
                'body' => $c->lastMessage->body,
                'sender_id' => $c->lastMessage->sender_id,
                'created_at' => $c->lastMessage->created_at->toISOString(),
            ] : null,
        ]);

        return Inertia::render('Admin/Conversations/Index', [
            'conversations' => $conversations,
            'filters' => [
                'id' => $request->id,
                'order_id' => $request->order_id,
                'type' => $request->type,
                'user_search' => $request->user_search,
            ],
        ]);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        $query = $conversation->messages()->with('sender')->latest();

        if ($request->before_id) {
            $query->where('id', '<', $request->before_id);
        }

        $messages = $query->take(50)->get()->reverse()->values();

        $hasMore = $messages->isNotEmpty() &&
            $conversation->messages()->where('id', '<', $messages->first()->id)->exists();

        $mapped = $messages->map(fn ($m) => [
            'id' => $m->id,
            'body' => $m->body,
            'type' => $m->type ?? 'user',
            'metadata' => $m->metadata,
            'sender_id' => $m->sender_id,
            'sender_name' => $m->sender?->name,
            'sender_avatar' => $m->sender?->avatar_url,
            'created_at' => $m->created_at->toISOString(),
        ]);

        return response()->json(['messages' => $mapped, 'has_more' => $hasMore]);
    }
}
