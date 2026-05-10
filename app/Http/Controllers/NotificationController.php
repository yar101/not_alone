<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const SERVICE_TYPES = ['idol_approved', 'idol_rejected', 'admin_broadcast', 'low_rating_warning', 'admin_rating', 'review_dispute_approved', 'review_dispute_rejected', 'content_pack_approved', 'content_pack_remarks', 'content_pack_rejected', 'content_pack_change_approved', 'content_pack_change_remarks', 'content_pack_change_rejected'];
    private const ORDER_TYPES   = ['order_created', 'order_accepted', 'order_cancelled', 'order_paid', 'order_completed'];
    private const MESSAGE_TYPES = ['new_message'];
    private const PER_PAGE      = 20;

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
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));

        $rows = $user->notifications()
            ->whereRaw("(data::jsonb->>'type') NOT IN ($placeholders)", $excluded)
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

        $unread = $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') NOT IN ($placeholders)", $excluded)
            ->count();

        return response()->json([
            'notifications' => $items,
            'unread_count'  => $unread,
            'has_more'      => $hasMore,
        ]);
    }

    public function service(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        $placeholders = implode(',', array_fill(0, count(self::SERVICE_TYPES), '?'));

        $rows = $user->notifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::SERVICE_TYPES)
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

        $unreadCount = $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::SERVICE_TYPES)
            ->count();

        return response()->json([
            'items'        => $items,
            'unread_count' => $unreadCount,
            'has_more'     => $hasMore,
        ]);
    }

    public function combined(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        $rows = $user->notifications()
            ->when($before, fn($q) => $q->where('created_at', '<', $before))
            ->latest()
            ->limit(self::PER_PAGE + 1)
            ->get();

        $hasMore = $rows->count() > self::PER_PAGE;
        $items   = $rows->take(self::PER_PAGE)->map(function ($n) {
            $type = $n->data['type'] ?? 'info';
            
            $cat = 'personal';
            if (in_array($type, self::ORDER_TYPES)) {
                $cat = 'order';
            } elseif (in_array($type, self::MESSAGE_TYPES) && ($n->data['sender_id'] ?? null)) {
                $cat = 'message';
            } elseif (in_array($type, self::SERVICE_TYPES) || in_array($type, self::MESSAGE_TYPES)) {
                $cat = 'service';
            }

            $base = [
                'id'         => $n->id,
                'type'       => $type,
                '_cat'       => $cat,
                'read_at'    => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'data'       => $n->data,
            ];

            if ($cat === 'order') {
                return array_merge($base, [
                    'order_id' => $n->data['order_id'] ?? null,
                ]);
            }

            return array_merge($base, [
                'source'  => 'notification',
                'title'   => $n->data['title'] ?? null,
                'message' => $n->data['message'] ?? '',
                'reason'  => $n->data['reason'] ?? null,
            ]);
        })->values();

        return response()->json(['items' => $items, 'has_more' => $hasMore]);
    }

    public function orders(Request $request)
    {
        $user   = $request->user();
        $before = $this->parseBefore($request);

        $placeholders = implode(',', array_fill(0, count(self::ORDER_TYPES), '?'));

        $rows = $user->notifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::ORDER_TYPES)
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
        ])->values();

        $unreadCount = $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::ORDER_TYPES)
            ->count();

        return response()->json([
            'items'        => $items,
            'unread_count' => $unreadCount,
            'has_more'     => $hasMore,
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user         = $request->user();
        $excluded     = array_merge(self::SERVICE_TYPES, self::ORDER_TYPES, self::MESSAGE_TYPES);
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));
        $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') NOT IN ($placeholders)", $excluded)
            ->get()
            ->each->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllOrdersRead(Request $request)
    {
        $user         = $request->user();
        $placeholders = implode(',', array_fill(0, count(self::ORDER_TYPES), '?'));
        $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::ORDER_TYPES)
            ->get()
            ->each->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllServiceRead(Request $request)
    {
        $user                = $request->user();
        $servicePlaceholders = implode(',', array_fill(0, count(self::SERVICE_TYPES), '?'));
        $messagePlaceholders = implode(',', array_fill(0, count(self::MESSAGE_TYPES), '?'));

        $user->unreadNotifications()
            ->where(function ($q) use ($servicePlaceholders, $messagePlaceholders) {
                $q->whereRaw("(data::jsonb->>'type') IN ($servicePlaceholders)", self::SERVICE_TYPES)
                    ->orWhere(function ($sq) use ($messagePlaceholders) {
                        $sq->whereRaw("(data::jsonb->>'type') IN ($messagePlaceholders)", self::MESSAGE_TYPES)
                            ->whereRaw("data::jsonb->>'sender_id' IS NULL");
                    });
            })
            ->get()
            ->each->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markAllMessagesRead(Request $request)
    {
        $user = $request->user();
        $placeholders = implode(',', array_fill(0, count(self::MESSAGE_TYPES), '?'));
        $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::MESSAGE_TYPES)
            ->whereRaw("data::jsonb->>'sender_id' IS NOT NULL")
            ->get()
            ->each->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function markBroadcastRead(Request $request, int $id)
    {
        return response()->json(['ok' => true]);
    }

    public function messages(Request $request)
    {
        return $this->service($request);
    }
}
