<?php

namespace App\Services;

use App\Models\IdolRatingLog;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Notifications\AdminRatingNotification;
use App\Notifications\LowRatingWarningNotification;

class IdolRatingService
{
    const EVENTS = [
        'would_buy_clicked' => 1,
        'report_accepted'   => -5,
        'post_published'    => 1,
        'admin_manual'      => null,
    ];

    public static function adjust(User $user, string $event, ?int $customDelta = null, ?string $note = null): void
    {
        $delta = $customDelta ?? (self::EVENTS[$event] ?? 0);

        if ($delta === null || $delta === 0) {
            return;
        }

        $oldRating = (int) ($user->rating ?? 20);
        $newRating = max(0, min(100, $oldRating + $delta));

        IdolRatingLog::create([
            'user_id'    => $user->id,
            'event'      => $event,
            'delta'      => $delta,
            'note'       => $note,
            'created_at' => now(),
        ]);

        $user->update(['rating' => $newRating]);

        // Notify idol about manual admin rating change
        if ($event === 'admin_manual') {
            $user->notify(new AdminRatingNotification($delta, $newRating, $note));
        }

        // If rating crossed the threshold downward, notify idol
        $threshold = (int) PlatformSetting::get('rating_low_threshold', 30);
        if ($oldRating >= $threshold && $newRating < $threshold) {
            $user->notify(new LowRatingWarningNotification($threshold));
        }
    }
}
