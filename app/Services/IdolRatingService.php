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
        'review_5star'            =>  0.8,
        'review_4star'            =>  0.4,
        'review_3star'            =>  0.0,
        'review_2star'            => -0.5,
        'review_1star'            => -1.2,
        'order_completed'         =>  0.2,
        'report_accepted'         => -2.0,
        'review_dispute_approved' =>  0.6,
        'admin_manual'            => null,
    ];

    public static function getEventDelta(string $event): float|null
    {
        $default = self::EVENTS[$event] ?? 0;
        if ($default === null) return null; // admin_manual — delta comes externally

        $stored = PlatformSetting::get('rating_delta_' . $event);
        return $stored !== null ? (float) $stored : (float) $default;
    }

    public static function adjust(User $user, string $event, ?float $customDelta = null, ?string $note = null): void
    {
        $delta = $customDelta ?? self::getEventDelta($event);

        if ($delta === null || $delta == 0) {
            return;
        }

        $oldRating = (float) ($user->rating ?? 20);

        // Positive delta has no effect if rating is already at maximum
        if ($delta > 0 && $oldRating >= 100) {
            return;
        }

        // Asymptotic dampening for positive deltas: harder to grow at high ratings
        if ($delta > 0) {
            $delta = max(0.01, round($delta * (100 - $oldRating) / 80, 2));
        }

        $newRating = max(0, min(100, round($oldRating + $delta, 2)));

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
