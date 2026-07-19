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
use Inertia\Inertia;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    use \App\Traits\NotificationTypes;

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

    public function share(Request $request): array
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('activeFrame');
        }
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
            'notifications_unread' => Inertia::defer(fn() => $user ? $this->hasUnreadNotifications($user) : false, 'badges'),
            'service_unread' => Inertia::defer(fn() => $user ? $this->hasUnreadService($user) : false, 'badges'),
            'order_notifications_unread' => Inertia::defer(fn() => $user ? $this->hasUnreadOrders($user) : false, 'badges'),
            'messages_notifications_unread' => Inertia::defer(fn() => $user ? $this->hasUnreadMessages($user) : false, 'badges'),
            'follows_unread' => Inertia::defer(fn() => $user ? $this->hasUnreadFollows($user) : false, 'badges'),
            'is_idol' => $user?->is_idol ?? false,
            'idol_status' => $idolStatus,
            'pending_applications_count' => Inertia::defer(fn() => auth('admin')->check()
                ? IdolApplication::where('status', 'pending')->count()
                : 0, 'admin_badges'),
            'pending_services_count' => Inertia::defer(fn() => auth('admin')->check()
                ? Service::where('status', 'pending')->count()
                : 0, 'admin_badges'),
            'pending_reports_count' => Inertia::defer(fn() => auth('admin')->check()
                ? UserReport::where('status', 'pending')->count()
                : 0, 'admin_badges'),
            'pending_trait_suggestions_count' => Inertia::defer(fn() => auth('admin')->check()
                ? TraitSuggestion::where('status', 'pending')->count()
                : 0, 'admin_badges'),
            'pending_interest_suggestions_count' => Inertia::defer(fn() => auth('admin')->check()
                ? InterestSuggestion::where('status', 'pending')->count()
                : 0, 'admin_badges'),
            'pending_review_disputes_count' => Inertia::defer(fn() => auth('admin')->check()
                ? ReviewDispute::where('status', 'pending')->count()
                : 0, 'admin_badges'),
            'has_unread_messages'  => Inertia::defer(fn() => $user ? $user->hasUnreadMessages() : false, 'badges'),
            'has_unread_direct'    => Inertia::defer(fn() => $user ? $user->hasUnreadDirect() : false, 'badges'),
            'has_unread_orders'    => Inertia::defer(fn() => $user ? $user->hasUnreadOrders() : false, 'badges'),
            'has_unread_mine'      => Inertia::defer(fn() => $user ? $user->hasUnreadMine() : false, 'badges'),
            'has_unread_incoming'  => Inertia::defer(fn() => $user ? $user->hasUnreadIncoming() : false, 'badges'),
            'chat_block_reasons' => fn() => $user
                ? cache()->rememberForever('chat_block_reasons_list', fn() => BanReason::forChatBlock()->get()->map(fn($r) => $r->label)->unique()->values())
                : [],
            'user_ban_reasons' => fn() => auth('admin')->check()
                ? cache()->rememberForever('user_ban_reasons_list', fn() => BanReason::forUserBan()->get()->map(fn($r) => $r->label)->unique()->values())
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

    private function hasUnreadNotifications($user): bool
    {
        $excluded = array_merge($this->SERVICE_TYPES, $this->ORDER_TYPES, $this->MESSAGE_TYPES, $this->FOLLOW_TYPES);
        $excludedClasses = $this->getClassesForTypes($excluded);
        return $user->unreadNotifications()
            ->whereNotIn('type', $excludedClasses)
            ->exists();
    }

    private function hasUnreadFollows($user): bool
    {
        $followClasses = $this->getClassesForTypes($this->FOLLOW_TYPES);
        return $user->unreadNotifications()
            ->whereIn('type', $followClasses)
            ->exists();
    }

    private function hasUnreadService($user): bool
    {
        $serviceClasses = $this->getClassesForTypes($this->SERVICE_TYPES);
        $messageClasses = $this->getClassesForTypes($this->MESSAGE_TYPES);
        
        // 1. Считаем персональные уведомления
        $hasPersonal = $user->unreadNotifications()
            ->where(function($q) use ($serviceClasses, $messageClasses) {
                $q->whereIn('type', $serviceClasses)
                  ->orWhere(function($sq) use ($messageClasses) {
                      $sq->whereIn('type', $messageClasses)
                         ->whereRaw("data::jsonb->>'sender_id' IS NULL");
                  });
            })
            ->exists();

        if ($hasPersonal) {
            return true;
        }

        // 2. Считаем общие рассылки, которые пользователь еще не читал
        return \App\Models\AdminBroadcast::where('target', '!=', 'user')
            ->forUser($user)
            ->whereDoesntHave('reads', fn($q) => $q->where('user_id', $user->id))
            ->exists();
    }

    private function hasUnreadOrders($user): bool
    {
        $orderClasses = $this->getClassesForTypes($this->ORDER_TYPES);
        return $user->unreadNotifications()
            ->whereIn('type', $orderClasses)
            ->exists();
    }

    private function hasUnreadMessages($user): bool
    {
        $messageClasses = $this->getClassesForTypes($this->MESSAGE_TYPES);
        return $user->unreadNotifications()
            ->whereIn('type', $messageClasses)
            ->whereRaw("data::jsonb->>'sender_id' IS NOT NULL")
            ->exists();
    }
}
