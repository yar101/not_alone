<?php

namespace App\Http\Middleware;

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
            'notifications_unread' => $user ? $this->countUnreadNotifications($user) : 0,
            'service_unread' => $user ? $this->countUnreadService($user) : 0,
            'is_idol' => $user?->is_idol ?? false,
            'idol_status' => $idolStatus,
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
