<?php

namespace App\Http\Middleware;

use App\Models\BanReason;
use App\Models\IdolApplication;
use App\Models\ReviewDispute;
use App\Models\Service;
use App\Models\InterestSuggestion;
use App\Models\TraitSuggestion;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

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

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $application = $user?->idolApplication;

        $idolStatus = null;
        if ($application) {
            $idolStatus = $application->status;
        }

        $unreadStrike = $user ? $user->unreadNotifications()->where('type', \App\Notifications\UserStrikeNotification::class)->first() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'unread_strike' => $unreadStrike ? [
                'id' => $unreadStrike->id,
                'data' => $unreadStrike->data,
            ] : null,
            'auth_admin' => auth('admin')->user(),
            'notifications_unread' => $user ? $this->countUnreadNotifications($user) : 0,
            'service_unread' => $user ? $this->countUnreadService($user) : 0,
            'order_notifications_unread' => $user ? $this->countUnreadOrders($user) : 0,
            'messages_notifications_unread' => $user ? $this->countUnreadMessages($user) : 0,
            'follows_unread' => $user ? $this->countUnreadFollows($user) : 0,
            'is_idol' => $user?->is_idol ?? false,
            'idol_status' => $idolStatus,
            'pending_applications_count' => fn() => auth('admin')->check()
                ? IdolApplication::where('status', 'pending')->count()
                : 0,
            'pending_services_count' => fn() => auth('admin')->check()
                ? Service::where('status', 'pending')->count()
                : 0,
            'pending_reports_count' => fn() => auth('admin')->check()
                ? UserReport::where('status', 'pending')->count()
                : 0,
            'pending_trait_suggestions_count' => fn() => auth('admin')->check()
                ? TraitSuggestion::where('status', 'pending')->count()
                : 0,
            'pending_interest_suggestions_count' => fn() => auth('admin')->check()
                ? InterestSuggestion::where('status', 'pending')->count()
                : 0,
            'pending_review_disputes_count' => fn() => auth('admin')->check()
                ? ReviewDispute::where('status', 'pending')->count()
                : 0,
            'unread_messages_count'  => fn() => $user?->unreadMessagesCount() ?? 0,
            'unread_direct_count'    => fn() => $user?->unreadDirectCount() ?? 0,
            'unread_orders_count'    => fn() => $user?->unreadOrdersCount() ?? 0,
            'unread_mine_count'      => fn() => $user?->unreadMineCount() ?? 0,
            'unread_incoming_count'  => fn() => $user?->unreadIncomingCount() ?? 0,
            'chat_block_reasons' => fn() => $user
                ? BanReason::forChatBlock()->get()->map(fn($r) => $r->label)->unique()->values()
                : [],
            'user_ban_reasons' => fn() => auth('admin')->check()
                ? BanReason::forUserBan()->get()->map(fn($r) => $r->label)->unique()->values()
                : [],
            'flash' => [
                'success'         => $request->session()->get('success'),
                'service_pending' => $request->session()->get('service_pending'),
            ],
            'locale' => [
                'current'      => app()->getLocale(),
                'available'    => config('app.available_locales'),
                'translations' => $this->getTranslations(),
            ],
            'vapid_public_key' => config('webpush.vapid.public_key'),
        ];
    }

    private function getTranslations(): array
    {
        $path = lang_path(app()->getLocale() . '.json');
        if (File::exists($path)) {
            return json_decode(File::get($path), true) ?? [];
        }
        return [];
    }

    private function countUnreadNotifications($user): int
    {
        $excluded = array_merge(self::SERVICE_TYPES, self::ORDER_TYPES, self::MESSAGE_TYPES, self::FOLLOW_TYPES);
        $placeholders = implode(',', array_fill(0, count($excluded), '?'));
        return $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') NOT IN ($placeholders)", $excluded)
            ->count();
    }

    private function countUnreadFollows($user): int
    {
        $placeholders = implode(',', array_fill(0, count(self::FOLLOW_TYPES), '?'));
        return $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::FOLLOW_TYPES)
            ->count();
    }

    private function countUnreadService($user): int
    {
        $servicePlaceholders = implode(',', array_fill(0, count(self::SERVICE_TYPES), '?'));
        $messagePlaceholders = implode(',', array_fill(0, count(self::MESSAGE_TYPES), '?'));
        
        // 1. Считаем персональные уведомления
        $count = $user->unreadNotifications()
            ->where(function($q) use ($servicePlaceholders, $messagePlaceholders) {
                $q->whereRaw("(data::jsonb->>'type') IN ($servicePlaceholders)", self::SERVICE_TYPES)
                  ->orWhere(function($sq) use ($messagePlaceholders) {
                      $sq->whereRaw("(data::jsonb->>'type') IN ($messagePlaceholders)", self::MESSAGE_TYPES)
                         ->whereRaw("data::jsonb->>'sender_id' IS NULL");
                  });
            })
            ->count();

        // 2. Считаем общие рассылки, которые пользователь еще не читал
        $broadcastsCount = \App\Models\AdminBroadcast::where('target', '!=', 'user')
            ->forUser($user)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
            ->count();

        return $count + $broadcastsCount;
    }

    private function countUnreadOrders($user): int
    {
        $placeholders = implode(',', array_fill(0, count(self::ORDER_TYPES), '?'));
        return $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::ORDER_TYPES)
            ->count();
    }

    private function countUnreadMessages($user): int
    {
        $placeholders = implode(',', array_fill(0, count(self::MESSAGE_TYPES), '?'));
        return $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::MESSAGE_TYPES)
            ->whereRaw("data::jsonb->>'sender_id' IS NOT NULL")
            ->count();
    }
}
