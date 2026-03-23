<?php

namespace App\Http\Middleware;

use App\Models\BanReason;
use App\Models\IdolApplication;
use App\Models\Service;
use App\Models\InterestSuggestion;
use App\Models\TraitSuggestion;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private const SERVICE_TYPES = ['idol_approved', 'idol_rejected', 'admin_broadcast', 'low_rating_warning', 'admin_rating'];

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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
            ],
            'auth_admin' => auth('admin')->user(),
            'notifications_unread' => $user ? $this->countUnreadNotifications($user) : 0,
            'service_unread' => $user ? $this->countUnreadService($user) : 0,
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
            'unread_messages_count' => fn() => $user?->unreadMessagesCount() ?? 0,
            'chat_block_reasons' => fn() => $user
                ? BanReason::forChatBlock()->pluck('label')
                : [],
            'user_ban_reasons' => fn() => auth('admin')->check()
                ? BanReason::forUserBan()->pluck('label')
                : [],
            'flash' => [
                'success'         => $request->session()->get('success'),
                'service_pending' => $request->session()->get('service_pending'),
            ],
        ];
    }

    private function countUnreadNotifications($user): int
    {
        $placeholders = implode(',', array_fill(0, count(self::SERVICE_TYPES), '?'));
        return $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') NOT IN ($placeholders)", self::SERVICE_TYPES)
            ->count();
    }

    private function countUnreadService($user): int
    {
        $placeholders = implode(',', array_fill(0, count(self::SERVICE_TYPES), '?'));
        return $user->unreadNotifications()
            ->whereRaw("(data::jsonb->>'type') IN ($placeholders)", self::SERVICE_TYPES)
            ->count();
    }
}
