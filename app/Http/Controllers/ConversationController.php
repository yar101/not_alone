<?php

namespace App\Http\Controllers;

use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\NewMessageReceived;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::whereNull('order_id')
            ->whereHas('participants', fn($q) => $q->where('user_id', $user->id))
            ->with([
                'participants.user',
                'lastMessage.sender',
            ])
            ->get()
            ->map(function (Conversation $conversation) use ($user) {
                $other = $conversation->participants
                    ->firstWhere('user_id', '!=', $user->id)?->user;

                $participantMe = $conversation->participants
                    ->firstWhere('user_id', $user->id);

                $unread = 0;
                if ($participantMe) {
                    $query = $conversation->messages()->where('sender_id', '!=', $user->id);
                    if ($participantMe->last_read_at) {
                        $query->where('created_at', '>', $participantMe->last_read_at);
                    }
                    $unread = $query->count();
                }

                return [
                    'id'           => $conversation->id,
                    'other_user'   => $other ? [
                        'id'         => $other->id,
                        'name'       => $other->name,
                        'avatar_url' => $other->avatar_url,
                        'is_idol'    => $other->is_idol,
                    ] : null,
                    'last_message' => $conversation->lastMessage ? [
                        'body'       => $conversation->lastMessage->body,
                        'sender_id'  => $conversation->lastMessage->sender_id,
                        'created_at' => $conversation->lastMessage->created_at?->toISOString(),
                    ] : null,
                    'unread_count' => $unread,
                    'updated_at'   => $conversation->updated_at?->toISOString(),
                    'block'        => $this->blockStatus($conversation, $user),
                ];
            })
            ->sortByDesc('updated_at')
            ->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        $query = $conversation->messages()->with('sender')->latest();
        if ($request->before_id) {
            $query->where('id', '<', $request->before_id);
        }
        $messages = $query->take(50)->get()->reverse()->values();

        $hasMore = $messages->isNotEmpty() &&
            $conversation->messages()->where('id', '<', $messages->first()->id)->exists();

        $mapped = $messages->map(fn($m) => [
            'id'              => $m->id,
            'body'            => $m->body,
            'type'            => $m->type ?? 'user',
            'metadata'        => $m->metadata,
            'sender_id'       => $m->sender_id,
            'sender_name'     => $m->sender->name,
            'sender_avatar'   => $m->sender->avatar_url,
            'created_at'      => $m->created_at->toISOString(),
            'conversation_id' => $m->conversation_id,
        ]);

        // Mark as read and broadcast
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);

        broadcast(new MessageRead($conversation->id, $user->id, now()->toISOString()));

        $otherParticipant = $conversation->participants()
            ->with('user')
            ->where('user_id', '!=', $user->id)
            ->first();

        $other = $otherParticipant?->user;

        $orderData = null;
        if ($conversation->order_id) {
            $conversation->load([
                'order.customer',
                'order.idol',
                'order.cancelledBy',
                'order.items.service.category',
                'order.items.service.timeUnit',
            ]);
            $o = $conversation->order;
            if ($o) {
                $orderData = [
                    'id'            => $o->id,
                    'status'        => $o->status,
                    'cancel_reason' => $o->cancel_reason,
                    'cancelled_by'      => $o->cancelled_by,
                    'cancelled_by_name' => $o->cancelledBy?->name,
                    'is_customer'   => $o->customer_id === $user->id,
                    'customer'      => ['id' => $o->customer->id, 'name' => $o->customer->name, 'avatar_url' => $o->customer->avatar_url],
                    'idol'          => ['id' => $o->idol->id, 'name' => $o->idol->name, 'avatar_url' => $o->idol->avatar_url, 'gender' => $o->idol->gender],
                    'items'         => $o->items->map(fn($item) => [
                        'id'      => $item->id,
                        'service' => $item->service ? [
                            'id'        => $item->service->id,
                            'name'      => $item->service->name,
                            'price'     => $item->service->price,
                            'time_unit' => $item->service->timeUnit?->name,
                        ] : null,
                    ])->values()->all(),
                ];
            }
        }

        return response()->json([
            'messages'           => $mapped,
            'other_user'         => $other ? [
                'id'         => $other->id,
                'name'       => $other->name,
                'avatar_url' => $other->avatar_url,
                'is_idol'    => $other->is_idol,
            ] : null,
            'other_last_read_at' => $otherParticipant?->last_read_at?->toISOString(),
            'has_more'           => $hasMore,
            'block'              => $this->blockStatus($conversation, $user),
            'order'              => $orderData,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_idol, 403);

        $request->validate(['target_user_id' => 'required|exists:users,id']);

        $target = User::findOrFail($request->target_user_id);
        $conversation = Conversation::findOrCreateBetween($request->user(), $target);

        return response()->json(['conversation_id' => $conversation->id]);
    }

    public function message(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        // Block messages in cancelled order conversations
        if ($conversation->order_id) {
            $conversation->loadMissing('order');
            if ($conversation->order?->status === 'cancelled') {
                abort(422, 'order_cancelled');
            }
        }

        $otherId = $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->value('user_id');

        $block = ChatBlock::active()
            ->where(['blocker_id' => $otherId, 'blocked_id' => $user->id])
            ->first();

        if ($block) {
            abort(403, 'blocked');
        }

        $request->validate(['body' => 'required|string|max:5000']);

        $msg = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body'      => $request->body,
        ]);

        $conversation->touch();

        $msg->load('sender');

        broadcast(new MessageSent($msg));

        // Notify each recipient via their private user channel (for badge update)
        $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id')
            ->each(fn($recipientId) => broadcast(new NewMessageReceived($recipientId, $conversation->id)));

        return response()->json([
            'id'             => $msg->id,
            'body'           => $msg->body,
            'sender_id'      => $msg->sender_id,
            'sender_name'    => $msg->sender->name,
            'sender_avatar'  => $msg->sender->avatar_url,
            'created_at'     => $msg->created_at->toISOString(),
            'conversation_id'=> $msg->conversation_id,
        ]);
    }

    public function block(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        $request->validate([
            'reason'   => 'required|string|max:255',
            'duration' => 'nullable|integer|min:1',
        ]);

        $otherId = $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->value('user_id');

        $blockedUntil = $request->duration
            ? now()->addMinutes($request->duration)
            : null;

        ChatBlock::updateOrCreate(
            ['blocker_id' => $user->id, 'blocked_id' => $otherId],
            ['reason' => $request->reason, 'blocked_until' => $blockedUntil]
        );

        return response()->json(['block' => $this->blockStatus($conversation, $user)]);
    }

    public function unblock(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        $otherId = $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->value('user_id');

        ChatBlock::where('blocker_id', $user->id)
            ->where('blocked_id', $otherId)
            ->delete();

        return response()->json(['block' => null]);
    }

    private function blockStatus(Conversation $conversation, User $user): ?array
    {
        $participants = $conversation->participants;
        $otherId = $participants->firstWhere('user_id', '!=', $user->id)?->user_id;

        if (!$otherId) return null;

        $block = ChatBlock::active()->where(function ($q) use ($user, $otherId) {
            $q->where(['blocker_id' => $user->id, 'blocked_id' => $otherId])
              ->orWhere(['blocker_id' => $otherId, 'blocked_id' => $user->id]);
        })->first();

        if (!$block) return null;

        return [
            'active'       => true,
            'i_am_blocker' => $block->blocker_id === $user->id,
            'reason'       => $block->reason,
            'blocked_until'=> $block->blocked_until?->toISOString(),
        ];
    }
}
