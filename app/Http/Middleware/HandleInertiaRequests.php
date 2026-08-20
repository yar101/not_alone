<?php

namespace App\Http\Middleware;

use App\Models\AdminBroadcast;
use App\Models\BanReason;
use App\Models\IdolApplication;
use App\Models\InterestSuggestion;
use App\Models\ReviewDispute;
use App\Models\Service;
use App\Models\TraitSuggestion;
use App\Models\User;
use App\Models\UserReport;
use App\Notifications\UserStrikeNotification;
use App\Traits\NotificationTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    use NotificationTypes;

    protected $rootView = 'app';

    public function handle(Request $request, \Closure $next)
    {
        $response = parent::handle($request, $next);

        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    private ?array $userBadgesCache = null;

    public function share(Request $request): array
    {
        $user = auth('web')->user();
        if ($user) {
            $user->loadMissing('activeFrame');
        }

        $idolStatus = null;
        if ($user) {
            if ($user->is_idol) {
                $idolStatus = 'approved';
            } else {
                $idolStatus = $user->idolApplication?->status;
            }
        }

        $unreadStrike = $user ? $user->unreadNotifications()->where('type', UserStrikeNotification::class)->first(['id', 'data']) : null;
        $isAdmin = auth('admin')->check();

        $userBadges = $user ? [
            'notifications_unread' => Inertia::defer(fn () => $this->getUserBadges($user)['notifications_unread'], 'badges'),
            'service_unread' => Inertia::defer(fn () => $this->getUserBadges($user)['service_unread'], 'badges'),
            'order_notifications_unread' => Inertia::defer(fn () => $this->getUserBadges($user)['order_notifications_unread'], 'badges'),
            'messages_notifications_unread' => Inertia::defer(fn () => $this->getUserBadges($user)['messages_notifications_unread'], 'badges'),
            'follows_unread' => Inertia::defer(fn () => $this->getUserBadges($user)['follows_unread'], 'badges'),
            'has_unread_messages' => Inertia::defer(fn () => $this->getUserBadges($user)['has_unread_messages'], 'badges'),
            'has_unread_direct' => Inertia::defer(fn () => $this->getUserBadges($user)['has_unread_direct'], 'badges'),
            'has_unread_orders' => Inertia::defer(fn () => $this->getUserBadges($user)['has_unread_orders'], 'badges'),
            'has_unread_mine' => Inertia::defer(fn () => $this->getUserBadges($user)['has_unread_mine'], 'badges'),
            'has_unread_incoming' => Inertia::defer(fn () => $this->getUserBadges($user)['has_unread_incoming'], 'badges'),
        ] : [
            'notifications_unread' => false,
            'service_unread' => false,
            'order_notifications_unread' => false,
            'messages_notifications_unread' => false,
            'follows_unread' => false,
            'has_unread_messages' => false,
            'has_unread_direct' => false,
            'has_unread_orders' => false,
            'has_unread_mine' => false,
            'has_unread_incoming' => false,
        ];

        $adminBadges = $isAdmin ? [
            'pending_applications_count' => Inertia::defer(fn () => IdolApplication::where('status', 'pending')->count(), 'admin_badges'),
            'pending_services_count' => Inertia::defer(fn () => Service::where('status', 'pending')->count(), 'admin_badges'),
            'pending_reports_count' => Inertia::defer(fn () => UserReport::where('status', 'pending')->count(), 'admin_badges'),
            'pending_trait_suggestions_count' => Inertia::defer(fn () => TraitSuggestion::where('status', 'pending')->count(), 'admin_badges'),
            'pending_interest_suggestions_count' => Inertia::defer(fn () => InterestSuggestion::where('status', 'pending')->count(), 'admin_badges'),
            'pending_review_disputes_count' => Inertia::defer(fn () => ReviewDispute::where('status', 'pending')->count(), 'admin_badges'),
        ] : [
            'pending_applications_count' => 0,
            'pending_services_count' => 0,
            'pending_reports_count' => 0,
            'pending_trait_suggestions_count' => 0,
            'pending_interest_suggestions_count' => 0,
            'pending_review_disputes_count' => 0,
        ];

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
            ...$userBadges,
            'is_idol' => $user?->is_idol ?? false,
            'idol_status' => $idolStatus,
            ...$adminBadges,
            'chat_block_reasons' => fn () => $user
                ? cache()->rememberForever('chat_block_reasons_list', fn () => BanReason::forChatBlock()->get()->map(fn ($r) => $r->label)->unique()->values())
                : [],
            'user_ban_reasons' => fn () => $isAdmin
                ? cache()->rememberForever('user_ban_reasons_list', fn () => BanReason::forUserBan()->get()->map(fn ($r) => $r->label)->unique()->values())
                : [],
            'flash' => [
                'success' => $request->session()->get('success'),
                'service_pending' => $request->session()->get('service_pending'),
            ],
            'locale' => [
                'current' => app()->getLocale(),
                'available' => config('app.available_locales'),
                'translations' => $this->getTranslations(),
            ],
            'vapid_public_key' => config('webpush.vapid.public_key'),
        ];
    }

    private function getTranslations(): array
    {
        $locale = app()->getLocale();

        return cache()->rememberForever("locale_translations_{$locale}", function () use ($locale) {
            $path = lang_path($locale.'.json');
            if (File::exists($path)) {
                return json_decode(File::get($path), true) ?? [];
            }

            return [];
        });
    }

    private function getUserBadges(User $user): array
    {
        if ($this->userBadgesCache !== null) {
            return $this->userBadgesCache;
        }

        // 1. Fetch unread database notifications (1 single query)
        $unreadNotifications = $user->unreadNotifications()->get(['id', 'type', 'data']);

        $serviceClasses = $this->getClassesForTypes($this->SERVICE_TYPES);
        $orderClasses = $this->getClassesForTypes($this->ORDER_TYPES);
        $messageClasses = $this->getClassesForTypes($this->MESSAGE_TYPES);
        $followClasses = $this->getClassesForTypes($this->FOLLOW_TYPES);
        $excludedClasses = array_merge($serviceClasses, $orderClasses, $messageClasses, $followClasses);

        $notificationsUnread = $unreadNotifications->contains(fn ($n) => ! in_array($n->type, $excludedClasses));
        $followsUnread = $unreadNotifications->contains(fn ($n) => in_array($n->type, $followClasses));
        $orderNotificationsUnread = $unreadNotifications->contains(fn ($n) => in_array($n->type, $orderClasses));
        $messagesNotificationsUnread = $unreadNotifications->contains(fn ($n) => in_array($n->type, $messageClasses) && ! empty($n->data['sender_id']));

        $hasPersonalService = $unreadNotifications->contains(function ($n) use ($serviceClasses, $messageClasses) {
            if (in_array($n->type, $serviceClasses)) {
                return true;
            }
            if (in_array($n->type, $messageClasses) && empty($n->data['sender_id'])) {
                return true;
            }

            return false;
        });

        $hasBroadcastService = false;
        if (! $hasPersonalService) {
            $hasBroadcastService = AdminBroadcast::where('target', '!=', 'user')
                ->forUser($user)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
                ->exists();
        }

        $serviceUnread = $hasPersonalService || $hasBroadcastService;

        // 2. Fetch unread conversation participants (1 single indexed query with eager loading)
        $unreadParticipants = $user->conversationParticipants()
            ->where('has_unread', true)
            ->with(['conversation.order:id,customer_id,idol_id'])
            ->get();

        $hasUnreadMessages = $unreadParticipants->isNotEmpty();
        $hasUnreadDirect = $unreadParticipants->contains(fn ($p) => $p->conversation && $p->conversation->order_id === null);
        $hasUnreadOrders = $unreadParticipants->contains(fn ($p) => $p->conversation && $p->conversation->order_id !== null);
        $hasUnreadMine = $unreadParticipants->contains(fn ($p) => $p->conversation?->order && (int) $p->conversation->order->customer_id === (int) $user->id);
        $hasUnreadIncoming = $unreadParticipants->contains(fn ($p) => $p->conversation?->order && (int) $p->conversation->order->idol_id === (int) $user->id);

        $this->userBadgesCache = [
            'notifications_unread' => $notificationsUnread,
            'service_unread' => $serviceUnread,
            'order_notifications_unread' => $orderNotificationsUnread,
            'messages_notifications_unread' => $messagesNotificationsUnread,
            'follows_unread' => $followsUnread,
            'has_unread_messages' => $hasUnreadMessages,
            'has_unread_direct' => $hasUnreadDirect,
            'has_unread_orders' => $hasUnreadOrders,
            'has_unread_mine' => $hasUnreadMine,
            'has_unread_incoming' => $hasUnreadIncoming,
        ];

        return $this->userBadgesCache;
    }
}
