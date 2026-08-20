<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\NewMessageReceived;
use App\Events\NewNotification;
use App\Models\ChatBlock;
use App\Models\Conversation;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ConversationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $search = trim($request->input('search', ''));
        $cursorAt = $request->input('cursor_at');
        $cursorId = (int) $request->input('cursor_id', 0);
        $perPage = 10;

        $query = Conversation::whereNull('order_id')
            ->withUser($user->id)
            ->with(['participants.user.activeFrame', 'lastMessage.sender.activeFrame'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($search !== '') {
            $query->search($search, $user->id);
        }

        if ($request->boolean('unread')) {
            $query->unreadForUser($user->id);
        }

        if ($cursorAt && $cursorId) {
            $query->where(function ($q) use ($cursorAt, $cursorId) {
                $q->where('updated_at', '<', $cursorAt)
                    ->orWhere(fn ($q) => $q->where('updated_at', $cursorAt)->where('id', '<', $cursorId));
            });
        }

        $items = $query->limit($perPage + 1)->get();
        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        $otherUserIds = $items->flatMap(fn ($c) => $c->participants)
            ->where('user_id', '!=', $user->id)
            ->pluck('user_id')
            ->unique();

        $activeBlocks = $otherUserIds->isNotEmpty()
            ? ChatBlock::active()->where(function ($q) use ($user, $otherUserIds) {
                $q->where('blocker_id', $user->id)->whereIn('blocked_id', $otherUserIds)
                    ->orWhere(fn ($sq) => $sq->whereIn('blocker_id', $otherUserIds)->where('blocked_id', $user->id));
            })->get()
            : collect();

        $conversations = $items->map(function (Conversation $conversation) use ($user, $activeBlocks) {
            $other = $conversation->participants
                ->firstWhere('user_id', '!=', $user->id)?->user;

            $participantMe = $conversation->participants
                ->firstWhere('user_id', $user->id);

            $hasUnread = $participantMe->has_unread ?? false;

            $blockData = null;
            if ($other) {
                $block = $activeBlocks->first(fn ($b) => ($b->blocker_id === $user->id && $b->blocked_id === $other->id) ||
                    ($b->blocker_id === $other->id && $b->blocked_id === $user->id)
                );
                if ($block) {
                    $blockData = [
                        'active' => true,
                        'i_am_blocker' => $block->blocker_id === $user->id,
                        'reason' => $block->reason,
                        'blocked_until' => $block->blocked_until?->toISOString(),
                    ];
                }
            }

            return [
                'id' => $conversation->id,
                'is_support' => (bool) $conversation->is_support,
                'closed_at' => $conversation->closed_at?->toISOString(),
                'other_user' => $other ? [
                    'id' => $other->id,
                    'name' => $other->name,
                    'avatar_url' => $other->avatar_url,
                    'active_frame' => $other->activeFrame,
                    'is_idol' => $other->is_idol,
                    'gender' => $other->gender,
                ] : null,
                'last_message' => $conversation->lastMessage ? [
                    'body' => $conversation->lastMessage->body,
                    'type' => $conversation->lastMessage->type,
                    'metadata' => $conversation->lastMessage->metadata,
                    'sender_id' => $conversation->lastMessage->sender_id,
                    'created_at' => $conversation->lastMessage->created_at?->toISOString(),
                ] : null,
                'unread' => $hasUnread,
                'updated_at' => $conversation->updated_at?->toISOString(),
                'block' => $blockData,
            ];
        })->values();

        return response()->json(['conversations' => $conversations, 'has_more' => $hasMore]);
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        $query = $conversation->messages()->with(['sender.activeFrame'])->latest();
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
            'sender_frame' => $m->sender?->activeFrame,
            'created_at' => $m->created_at->toISOString(),
            'conversation_id' => $m->conversation_id,
        ]);

        // Mark as read and broadcast
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update([
                'last_read_at' => now(),
                'has_unread' => false,
            ]);

        broadcast(new MessageRead($conversation->id, $user->id, now()->toISOString()));

        $otherParticipant = $conversation->participants()
            ->with('user')
            ->where('user_id', '!=', $user->id)
            ->first();

        $other = $otherParticipant?->user;

        $orderData = null;
        if ($conversation->order_id) {
            $conversation->load([
                'order.customer.activeFrame',
                'order.idol.activeFrame',
                'order.cancelledBy',
                'order.items.service.category',
                'order.items.service.timeUnit',
            ]);
            $o = $conversation->order;
            if ($o) {
                $orderData = [
                    'id' => $o->id,
                    'status' => $o->status->value,
                    'cancel_reason' => $o->cancel_reason,
                    'cancelled_by' => $o->cancelled_by,
                    'cancelled_by_name' => $o->cancelledBy?->name,
                    'is_customer' => $o->customer_id === $user->id,
                    'paid_at' => $o->paid_at?->toISOString(),
                    'auto_complete_at' => $o->paid_at ? $o->paid_at->addSeconds((int) ((float) PlatformSetting::get('order_auto_complete_delay', 72) * 3600))->toISOString() : null,
                    'completed_at' => $o->completed_at?->toISOString(),
                    'completion_confirmed_by_idol' => $o->completion_confirmed_by_idol,
                    'completion_confirmed_by_customer' => $o->completion_confirmed_by_customer,
                    'customer' => ['id' => $o->customer->id, 'name' => $o->customer->name, 'avatar_url' => $o->customer->avatar_url, 'active_frame' => $o->customer->activeFrame],
                    'idol' => ['id' => $o->idol->id, 'name' => $o->idol->name, 'avatar_url' => $o->idol->avatar_url, 'active_frame' => $o->idol->activeFrame, 'gender' => $o->idol->gender],
                    'items' => $o->items->map(fn ($item) => [
                        'id' => $item->id,
                        'quantity' => $item->quantity ?? 1,
                        'service' => $item->service ? [
                            'id' => $item->service->id,
                            'name' => $item->service->name,
                            'price' => $item->service->price,
                            'time_unit' => $item->service->timeUnit?->name,
                        ] : null,
                    ])->values()->all(),
                ];
            }
        }

        $hasReview = $conversation->order_id
            ? \App\Models\Review::where('reviewer_id', $user->id)
                ->where('idol_id', $conversation->order?->idol_id)
                ->exists()
            : false;

        return response()->json([
            'messages' => $mapped,
            'other_user' => $other ? [
                'id' => $other->id,
                'name' => $other->name,
                'avatar_url' => $other->avatar_url,
                'active_frame' => $other->activeFrame,
                'is_idol' => $other->is_idol,
                'gender' => $other->gender,
            ] : null,            'other_last_read_at' => $otherParticipant?->last_read_at?->toISOString(),
            'has_more' => $hasMore,
            'block' => $this->blockStatus($conversation, $user),
            'order' => $orderData,
            'is_support' => (bool) $conversation->is_support,
            'closed_at' => $conversation->closed_at?->toISOString(),
            'has_review' => $hasReview,
        ]);
    }

    public function check(Request $request, User $user): JsonResponse
    {
        $auth = $request->user();
        abort_unless($auth->is_idol, 403);
        abort_if($user->is_idol, 422, 'target_is_idol');

        $conversation = Conversation::whereNull('order_id')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $auth->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        $block = ChatBlock::active()->where(function ($q) use ($auth, $user) {
            $q->where(['blocker_id' => $auth->id, 'blocked_id' => $user->id])
                ->orWhere(['blocker_id' => $user->id, 'blocked_id' => $auth->id]);
        })->first();

        $blockData = $block ? [
            'active' => true,
            'i_am_blocker' => $block->blocker_id === $auth->id,
            'reason' => $block->reason,
            'blocked_until' => $block->blocked_until?->toISOString(),
        ] : null;

        return response()->json([
            'conversation_id' => $conversation?->id,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar_url' => $user->avatar_url,
                'is_idol' => $user->is_idol,
                'gender' => $user->gender,
            ],
            'block' => $blockData,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->is_idol, 403);

        $request->validate(['target_user_id' => 'required|exists:users,id']);

        $target = User::findOrFail($request->target_user_id);
        abort_if($target->isActiveBanned(), 422, 'user_banned');
        abort_if($target->is_idol, 422, 'target_is_idol');
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

        // Block messages in closed support conversations
        abort_if($conversation->closed_at !== null, 422, 'chat_closed');

        // Block messages in cancelled or completed order conversations
        if ($conversation->order_id) {
            $conversation->loadMissing('order');
            $status = $conversation->order?->status;
            if ($status === OrderStatus::Cancelled) {
                abort(422, 'order_cancelled');
            }
            if ($status === OrderStatus::Completed) {
                abort(422, 'order_completed');
            }
            if ($status === OrderStatus::Disputed) {
                abort(422, 'order_disputed');
            }
            if ($status === OrderStatus::Refunded) {
                abort(422, 'order_refunded');
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

        $other = User::select(['id', 'is_banned', 'banned_until'])->find($otherId);
        if ($other && $other->isActiveBanned()) {
            abort(422, 'user_banned');
        }

        $type = $request->input('type', 'user');

        $request->validate([
            'body' => $type === 'image' ? 'nullable|string|max:5000' : 'required|string|max:5000',
            'type' => 'nullable|string|in:user,image',
            'metadata' => 'nullable|array',
        ]);

        $msg = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $request->body ?? '',
            'type' => $type,
            'metadata' => $request->metadata,
        ]);

        $conversation->touch();
        $conversation->participants()->where('user_id', '!=', $user->id)->update(['has_unread' => true]);

        $msg->load('sender');

        broadcast(new MessageSent($msg));

        // Notify each recipient via their private user channel (for badge update)
        $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get()
            ->each(function ($participant) use ($conversation, $msg) {
                broadcast(new NewMessageReceived(
                    $participant->user_id,
                    $conversation->id,
                    $msg,
                    $conversation->order_id,
                ));

                $participant->user->notify(new NewMessageNotification($msg));
                broadcast(new NewNotification('private', $participant->user_id));
            });

        return response()->json([
            'id' => $msg->id,
            'body' => $msg->body,
            'type' => $msg->type,
            'metadata' => $msg->metadata,
            'sender_id' => $msg->sender_id,
            'sender_name' => $msg->sender->name,
            'sender_avatar' => $msg->sender->avatar_url,
            'created_at' => $msg->created_at->toISOString(),
            'conversation_id' => $msg->conversation_id,
        ]);
    }

    public function offerServices(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );
        abort_unless($user->is_idol, 403);

        if ($conversation->order_id) {
            $order = $conversation->order;
            abort_unless($order && $order->status === OrderStatus::Pending, 422, 'Предлагать услуги можно только для заказов со статусом «Создан»');
        }

        $request->validate([
            'services' => ['required', 'array', 'min:1', 'max:2'],
            'services.*' => ['required', 'integer', 'exists:services,id'],
        ]);

        $services = \App\Models\Service::whereIn('id', $request->services)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['category:id,name', 'timeUnit:id,name'])
            ->get();

        abort_if($services->count() !== count(array_unique($request->services)), 422, 'Некоторые услуги недоступны');

        $msg = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => '',
            'type' => 'service_offer',
            'metadata' => [
                'services' => $services->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'price' => $s->price,
                    'time_unit' => $s->timeUnit?->name,
                    'category_name' => $s->category?->name,
                ])->values()->all(),
            ],
        ]);

        $conversation->touch();
        $conversation->participants()->where('user_id', '!=', $user->id)->update(['has_unread' => true]);
        $msg->load('sender');

        try {
            broadcast(new MessageSent($msg));
        } catch (\Throwable $e) {
            \Log::warning('Broadcast failed: '.$e->getMessage());
        }

        $conversation->participants()
            ->where('user_id', '!=', $user->id)
            ->with('user')
            ->get()
            ->each(function ($participant) use ($conversation, $msg) {
                try {
                    broadcast(new NewMessageReceived($participant->user_id, $conversation->id, $msg));
                } catch (\Throwable $e) {
                    \Log::warning('Broadcast NewMessageReceived failed: '.$e->getMessage());
                }

                $participant->user->notify(new NewMessageNotification($msg));
                broadcast(new NewNotification('private', $participant->user_id));
            });

        return response()->json([
            'id' => $msg->id,
            'body' => $msg->body,
            'type' => $msg->type,
            'metadata' => $msg->metadata,
            'sender_id' => $msg->sender_id,
            'sender_name' => $msg->sender->name,
            'sender_avatar' => $msg->sender->avatar_url,
            'created_at' => $msg->created_at->toISOString(),
            'conversation_id' => $msg->conversation_id,
        ]);
    }

    public function upload(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:5120'],
        ]);

        $path = $request->file('file')->store("chat/{$conversation->id}");

        return response()->json(['url' => Storage::url($path)]);
    }

    public function block(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $conversation->participants()->where('user_id', $user->id)->exists(),
            403
        );

        $request->validate([
            'reason' => 'required|string|max:255',
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

        if (! $otherId) {
            return null;
        }

        $block = ChatBlock::active()->where(function ($q) use ($user, $otherId) {
            $q->where(['blocker_id' => $user->id, 'blocked_id' => $otherId])
                ->orWhere(['blocker_id' => $otherId, 'blocked_id' => $user->id]);
        })->first();

        if (! $block) {
            return null;
        }

        return [
            'active' => true,
            'i_am_blocker' => $block->blocker_id === $user->id,
            'reason' => $block->reason,
            'blocked_until' => $block->blocked_until?->toISOString(),
        ];
    }
}
