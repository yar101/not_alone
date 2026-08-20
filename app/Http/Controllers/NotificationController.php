<?php

namespace App\Http\Controllers;

use App\Models\AdminBroadcast;
use App\Models\AdminBroadcastRead;
use App\Traits\NotificationTypes;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use NotificationTypes;

    private const PER_PAGE = 20;

    private function parseBefore(Request $request): ?Carbon
    {
        $raw = $request->input('before');

        return $raw ? Carbon::parse($raw) : null;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $before = $this->parseBefore($request);

        $excluded = array_merge($this->SERVICE_TYPES, $this->ORDER_TYPES, $this->MESSAGE_TYPES);
        $excludedClasses = $this->getClassesForTypes($excluded);

        $rows = $user->notifications()
            ->whereNotIn('type', $excludedClasses)
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items = $rows->take(self::PER_PAGE)->map(fn ($n) => [
            'id' => $n->id,
            'source' => 'notification',
            'type' => $n->data['type'] ?? 'info',
            'message' => $n->data['message'] ?? '',
            'reason' => $n->data['reason'] ?? null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ])->values();

        $hasUnread = $user->unreadNotifications()
            ->whereNotIn('type', $excludedClasses)
            ->exists();

        return response()->json([
            'notifications' => $items,
            'has_unread' => $hasUnread,
            'has_more' => $hasMore,
        ]);
    }

    public function service(Request $request)
    {
        $user = $request->user();
        $before = $this->parseBefore($request);

        $serviceClasses = $this->getClassesForTypes($this->SERVICE_TYPES);

        $rows = $user->notifications()
            ->whereIn('type', $serviceClasses)
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items = $rows->take(self::PER_PAGE)->map(fn ($n) => [
            'id' => $n->id,
            'source' => 'notification',
            'type' => $n->data['type'] ?? null,
            'title' => $n->data['title'] ?? null,
            'message' => $n->data['message'] ?? '',
            'reason' => $n->data['reason'] ?? null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ])->values();

        $hasUnread = $user->unreadNotifications()
            ->whereIn('type', $serviceClasses)
            ->exists();

        return response()->json([
            'items' => $items,
            'has_unread' => $hasUnread,
            'has_more' => $hasMore,
        ]);
    }

    public function combined(Request $request)
    {
        $user = $request->user();
        $before = $this->parseBefore($request);

        // 1. Персональные уведомления
        $notifications = $user->notifications()
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE)
            ->get();

        // 2. Подходящие общие рассылки
        $broadcasts = AdminBroadcast::where('target', '!=', 'user')
            ->forUser($user)
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE)
            ->get();

        // 3. Объединение и сортировка
        $broadcastIds = $broadcasts->pluck('id');
        $readsMap = $broadcastIds->isNotEmpty()
            ? AdminBroadcastRead::whereIn('broadcast_id', $broadcastIds)
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('broadcast_id')
            : collect();

        $combined = $notifications->map(function ($n) {
            $type = $n->data['type'] ?? 'info';
            $cat = $this->categorize($type, $n->data['sender_id'] ?? null);

            return [
                'id' => $n->id,
                'type' => $type,
                '_cat' => $cat,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'data' => $n->data,
                'reason' => $n->data['reason'] ?? $n->data['rejection_reason'] ?? null,
                'source' => 'notification',
            ];
        })->concat($broadcasts->map(function ($b) use ($readsMap) {
            $read = $readsMap->get($b->id);

            return [
                'id' => "bc_{$b->id}",
                'broadcast_id' => $b->id,
                'type' => 'admin_broadcast',
                '_cat' => 'service',
                'read_at' => $read?->read_at?->toIso8601String(),
                'created_at' => $b->created_at->toIso8601String(),
                'data' => [
                    'type' => 'admin_broadcast',
                    'broadcast_id' => $b->id,
                    'title_locales' => $b->getTranslations('title'),
                    'body_locales' => $b->getTranslations('body'),
                ],
                'source' => 'broadcast',
            ];
        }))->sortByDesc('created_at')->values();

        $hasMore = $combined->count() > self::PER_PAGE;
        $items = $combined->take(self::PER_PAGE);

        return response()->json(['items' => $items, 'has_more' => $hasMore]);
    }

    private function categorize(string $type, $senderId): string
    {
        if (in_array($type, $this->ORDER_TYPES)) {
            return 'order';
        }
        if (in_array($type, $this->MESSAGE_TYPES) && $senderId) {
            return 'message';
        }
        if (in_array($type, $this->FOLLOW_TYPES)) {
            return 'follow';
        }
        if (in_array($type, $this->SERVICE_TYPES) || in_array($type, $this->MESSAGE_TYPES)) {
            return 'service';
        }

        return 'personal';
    }

    public function orders(Request $request)
    {
        $user = $request->user();
        $before = $this->parseBefore($request);

        $orderClasses = $this->getClassesForTypes($this->ORDER_TYPES);

        $rows = $user->notifications()
            ->whereIn('type', $orderClasses)
            ->when($before, fn ($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items = $rows->take(self::PER_PAGE)->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->data['type'],
            'order_id' => $n->data['order_id'] ?? null,
            'read_at' => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'data' => $n->data,
            'reason' => $n->data['reason'] ?? $n->data['rejection_reason'] ?? null,
        ])->values();

        $hasUnread = $user->unreadNotifications()
            ->whereIn('type', $orderClasses)
            ->exists();

        return response()->json([
            'items' => $items,
            'has_unread' => $hasUnread,
            'has_more' => $hasMore,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        if (str_starts_with($id, 'bc_')) {
            $broadcastId = (int) substr($id, 3);

            return $this->markBroadcastRead($request, $broadcastId);
        }

        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        $excluded = array_merge($this->SERVICE_TYPES, $this->ORDER_TYPES, $this->MESSAGE_TYPES, $this->FOLLOW_TYPES);
        $excludedClasses = $this->getClassesForTypes($excluded);

        $user->unreadNotifications()
            ->whereNotIn('type', $excludedClasses)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllOrdersRead(Request $request)
    {
        $user = $request->user();
        $orderClasses = $this->getClassesForTypes($this->ORDER_TYPES);

        $user->unreadNotifications()
            ->whereIn('type', $orderClasses)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllServiceRead(Request $request)
    {
        $user = $request->user();
        $serviceClasses = $this->getClassesForTypes($this->SERVICE_TYPES);
        $messageClasses = $this->getClassesForTypes($this->MESSAGE_TYPES);

        // 1. Помечаем персональные уведомления
        $user->unreadNotifications()
            ->where(function ($q) use ($serviceClasses, $messageClasses) {
                $q->whereIn('type', $serviceClasses)
                    ->orWhere(function ($sq) use ($messageClasses) {
                        $sq->whereIn('type', $messageClasses)
                            ->whereRaw("data::jsonb->>'sender_id' IS NULL");
                    });
            })
            ->update(['read_at' => now()]);

        // 2. Помечаем подходящие общие рассылки в один UPSERT
        $broadcasts = AdminBroadcast::where('target', '!=', 'user')->forUser($user)->get(['id']);
        if ($broadcasts->isNotEmpty()) {
            $records = $broadcasts->map(fn ($b) => [
                'broadcast_id' => $b->id,
                'user_id' => $user->id,
                'read_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();
            AdminBroadcastRead::upsert($records, ['broadcast_id', 'user_id'], ['read_at', 'updated_at']);
        }

        return response()->json(['ok' => true]);
    }

    public function markAllFollowsRead(Request $request)
    {
        $user = $request->user();
        $followClasses = $this->getClassesForTypes($this->FOLLOW_TYPES);

        $user->unreadNotifications()
            ->whereIn('type', $followClasses)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllMessagesRead(Request $request)
    {
        $user = $request->user();
        $messageClasses = $this->getClassesForTypes($this->MESSAGE_TYPES);

        $user->unreadNotifications()
            ->whereIn('type', $messageClasses)
            ->whereRaw("data::jsonb->>'sender_id' IS NOT NULL")
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markBroadcastRead(Request $request, int $id)
    {
        $user = $request->user();
        AdminBroadcastRead::updateOrCreate(
            ['broadcast_id' => $id, 'user_id' => $user->id],
            ['read_at' => now()]
        );

        return response()->json(['ok' => true]);
    }

    public function messages(Request $request)
    {
        return $this->service($request);
    }
}
