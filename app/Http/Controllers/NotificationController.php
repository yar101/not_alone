<?php

namespace App\Http\Controllers;

use App\Models\AdminBroadcast;
use App\Models\AdminBroadcastRead;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const SERVICE_TYPES = [
        'idol_approved', 'idol_rejected', 'admin_broadcast', 'low_rating_warning', 'admin_rating',
        'review_dispute_approved', 'review_dispute_rejected', 'content_pack_approved',
        'content_pack_remarks', 'content_pack_rejected', 'content_pack_change_approved',
        'content_pack_change_remarks', 'content_pack_change_rejected',
        'service_approved', 'service_rejected', 'service_remarks',
        'service_change_approved', 'service_change_remarks', 'service_change_rejected',
        'test', 'chat_status', 'new_review'
    ];
    private const ORDER_TYPES   = ['order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'];
    private const MESSAGE_TYPES = ['new_message'];
    private const FOLLOW_TYPES  = ['new_post', 'new_service', 'new_content_pack'];
    private const PER_PAGE      = 20;

    private const TYPE_MAP = [
        'idol_approved' => \App\Notifications\IdolApprovedNotification::class,
        'idol_rejected' => \App\Notifications\IdolRejectedNotification::class,
        'low_rating_warning' => \App\Notifications\LowRatingWarningNotification::class,
        'admin_rating' => \App\Notifications\AdminRatingNotification::class,
        'review_dispute_approved' => \App\Notifications\ReviewDisputeApprovedNotification::class,
        'review_dispute_rejected' => \App\Notifications\ReviewDisputeRejectedNotification::class,
        'content_pack_approved' => \App\Notifications\ContentPackApprovedNotification::class,
        'content_pack_remarks' => \App\Notifications\ContentPackRemarksNotification::class,
        'content_pack_rejected' => \App\Notifications\ContentPackRejectedNotification::class,
        'content_pack_change_approved' => \App\Notifications\ContentPackChangeApprovedNotification::class,
        'content_pack_change_remarks' => \App\Notifications\ContentPackChangeRemarksNotification::class,
        'content_pack_change_rejected' => \App\Notifications\ContentPackChangeRejectedNotification::class,
        'service_approved' => \App\Notifications\ServiceApprovedNotification::class,
        'service_rejected' => \App\Notifications\ServiceRejectedNotification::class,
        'service_remarks' => \App\Notifications\ServiceRemarksNotification::class,
        'service_change_approved' => \App\Notifications\ServiceChangeApprovedNotification::class,
        'service_change_remarks' => \App\Notifications\ServiceChangeRemarksNotification::class,
        'service_change_rejected' => \App\Notifications\ServiceChangeRejectedNotification::class,
        'test' => \App\Notifications\TestNotification::class,
        'chat_status' => \App\Notifications\ChatStatusNotification::class,
        'new_review' => \App\Notifications\NewReviewNotification::class,
        'order_created' => \App\Notifications\OrderCreatedNotification::class,
        'order_accepted' => \App\Notifications\OrderAcceptedNotification::class,
        'order_cancelled' => \App\Notifications\OrderCancelledNotification::class,
        'order_paid' => \App\Notifications\OrderPaidNotification::class,
        'order_completed' => \App\Notifications\OrderCompletedNotification::class,
        'new_message' => \App\Notifications\NewMessageNotification::class,
        'new_post' => \App\Notifications\NewPostNotification::class,
        'new_service' => \App\Notifications\NewServiceNotification::class,
        'new_content_pack' => \App\Notifications\NewContentPackNotification::class,
        'strike' => \App\Notifications\UserStrikeNotification::class,
    ];

    private function getClassesForTypes(array $types): array
    {
        $classes = [];
        foreach ($types as $type) {
            if (isset(self::TYPE_MAP[$type])) {
                $classes[] = self::TYPE_MAP[$type];
            }
        }
        return $classes;
    }

    private function parseBefore(Request $request): ?Carbon
    {
        $raw = $request->input('before');
        return $raw ? Carbon::parse($raw) : null;
    }

    public function index(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        $excluded     = array_merge(self::SERVICE_TYPES, self::ORDER_TYPES, self::MESSAGE_TYPES);
        $excludedClasses = $this->getClassesForTypes($excluded);

        $rows = $user->notifications()
            ->whereNotIn('type', $excludedClasses)
            ->when($before, fn($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items   = $rows->take(self::PER_PAGE)->map(fn($n) => [
            'id'         => $n->id,
            'source'     => 'notification',
            'type'       => $n->data['type'] ?? 'info',
            'message'    => $n->data['message'] ?? '',
            'reason'     => $n->data['reason'] ?? null,
            'read_at'    => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ])->values();

        $hasUnread = $user->unreadNotifications()
            ->whereNotIn('type', $excludedClasses)
            ->exists();

        return response()->json([
            'notifications' => $items,
            'has_unread'    => $hasUnread,
            'has_more'      => $hasMore,
        ]);
    }

    public function service(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        $serviceClasses = $this->getClassesForTypes(self::SERVICE_TYPES);

        $rows = $user->notifications()
            ->whereIn('type', $serviceClasses)
            ->when($before, fn($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items   = $rows->take(self::PER_PAGE)->map(fn($n) => [
            'id'         => $n->id,
            'source'     => 'notification',
            'type'       => $n->data['type'] ?? null,
            'title'      => $n->data['title'] ?? null,
            'message'    => $n->data['message'] ?? '',
            'reason'     => $n->data['reason'] ?? null,
            'read_at'    => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
        ])->values();

        $hasUnread = $user->unreadNotifications()
            ->whereIn('type', $serviceClasses)
            ->exists();

        return response()->json([
            'items'      => $items,
            'has_unread' => $hasUnread,
            'has_more'   => $hasMore,
        ]);
    }

    public function combined(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        // 1. Персональные уведомления
        $notifications = $user->notifications()
            ->when($before, fn($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE)
            ->get();

        // 2. Подходящие общие рассылки
        $broadcasts = AdminBroadcast::where('target', '!=', 'user')
            ->forUser($user)
            ->when($before, fn($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE)
            ->get();

        // 3. Объединение и сортировка
        $combined = $notifications->map(function ($n) {
            $type = $n->data['type'] ?? 'info';
            $cat = $this->categorize($type, $n->data['sender_id'] ?? null);
            
            return [
                'id'         => $n->id,
                'type'       => $type,
                '_cat'       => $cat,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'data'       => $n->data,
                'reason'     => $n->data['reason'] ?? $n->data['rejection_reason'] ?? null,
                'source'     => 'notification',
            ];
        })->concat($broadcasts->map(function ($b) use ($user) {
            $read = $b->reads()->where('user_id', $user->id)->first();
            
            return [
                'id'         => "bc_{$b->id}", 
                'broadcast_id' => $b->id,
                'type'       => 'admin_broadcast',
                '_cat'       => 'service',
                'read_at'    => $read?->read_at?->toIso8601String(),
                'created_at' => $b->created_at->toIso8601String(),
                'data'       => [
                    'type'          => 'admin_broadcast',
                    'broadcast_id'  => $b->id,
                    'title_locales' => $b->getTranslations('title'),
                    'body_locales'  => $b->getTranslations('body'),
                ],
                'source'     => 'broadcast',
            ];
        }))->sortByDesc('created_at')->values();

        $hasMore = $combined->count() > self::PER_PAGE;
        $items = $combined->take(self::PER_PAGE);

        return response()->json(['items' => $items, 'has_more' => $hasMore]);
    }

    private function categorize(string $type, $senderId): string
    {
        if (in_array($type, self::ORDER_TYPES)) {
            return 'order';
        } 
        if (in_array($type, self::MESSAGE_TYPES) && $senderId) {
            return 'message';
        } 
        if (in_array($type, self::FOLLOW_TYPES)) {
            return 'follow';
        }
        if (in_array($type, self::SERVICE_TYPES) || in_array($type, self::MESSAGE_TYPES)) {
            return 'service';
        }
        return 'personal';
    }

    public function orders(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        $orderClasses = $this->getClassesForTypes(self::ORDER_TYPES);

        $rows = $user->notifications()
            ->whereIn('type', $orderClasses)
            ->when($before, fn($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items   = $rows->take(self::PER_PAGE)->map(fn($n) => [
            'id'         => $n->id,
            'type'       => $n->data['type'],
            'order_id'   => $n->data['order_id'] ?? null,
            'read_at'    => $n->read_at?->toIso8601String(),
            'created_at' => $n->created_at->toIso8601String(),
            'data'       => $n->data,
                'reason'     => $n->data['reason'] ?? $n->data['rejection_reason'] ?? null,
        ])->values();

        $hasUnread = $user->unreadNotifications()
            ->whereIn('type', $orderClasses)
            ->exists();

        return response()->json([
            'items'      => $items,
            'has_unread' => $hasUnread,
            'has_more'   => $hasMore,
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
        $user         = $request->user();
        $excluded     = array_merge(self::SERVICE_TYPES, self::ORDER_TYPES, self::MESSAGE_TYPES, self::FOLLOW_TYPES);
        $excludedClasses = $this->getClassesForTypes($excluded);
        
        $user->unreadNotifications()
            ->whereNotIn('type', $excludedClasses)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllOrdersRead(Request $request)
    {
        $user         = $request->user();
        $orderClasses = $this->getClassesForTypes(self::ORDER_TYPES);
        
        $user->unreadNotifications()
            ->whereIn('type', $orderClasses)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllServiceRead(Request $request)
    {
        $user                = $request->user();
        $serviceClasses = $this->getClassesForTypes(self::SERVICE_TYPES);
        $messageClasses = $this->getClassesForTypes(self::MESSAGE_TYPES);

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

        // 2. Помечаем подходящие общие рассылки
        $broadcasts = AdminBroadcast::where('target', '!=', 'user')->forUser($user)->get();
        foreach ($broadcasts as $b) {
            AdminBroadcastRead::updateOrCreate(
                ['broadcast_id' => $b->id, 'user_id' => $user->id],
                ['read_at' => now()]
            );
        }

        return response()->json(['ok' => true]);
    }

    public function markAllFollowsRead(Request $request)
    {
        $user         = $request->user();
        $followClasses = $this->getClassesForTypes(self::FOLLOW_TYPES);
        
        $user->unreadNotifications()
            ->whereIn('type', $followClasses)
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllMessagesRead(Request $request)
    {
        $user = $request->user();
        $messageClasses = $this->getClassesForTypes(self::MESSAGE_TYPES);
        
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
