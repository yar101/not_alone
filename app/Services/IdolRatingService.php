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
        'review_5star' => 0.8,
        'review_4star' => 0.4,
        'review_3star' => 0.0,
        'review_2star' => -0.5,
        'review_1star' => -1.2,
        'order_completed' => 0.2,
        'report_accepted' => -2.0,
        'review_dispute_approved' => 0.6,
        'strike' => null,
        'admin_manual' => null,
    ];

    public static function getEventDelta(string $event): ?float
    {
        $default = self::EVENTS[$event] ?? 0;
        if ($default === null) {
            return null;
        } // admin_manual — delta comes externally

        $stored = PlatformSetting::get('rating_delta_'.$event);

        return $stored !== null ? (float) $stored : (float) $default;
    }

    public static function adjust(User $user, string $event, ?float $customDelta = null, ?string $note = null): void
    {
        $delta = $customDelta ?? self::getEventDelta($event);

        if ($delta === null || $delta == 0) {
            return;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($user, $event, $delta, $note) {
            $lockedUser = User::lockForUpdate()->find($user->id);
            if (! $lockedUser) {
                return;
            }

            $oldRating = (float) ($lockedUser->rating ?? 20);

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
                'user_id' => $lockedUser->id,
                'event' => $event,
                'delta' => $delta,
                'note' => $note,
                'created_at' => now(),
            ]);

            $lockedUser->update(['rating' => $newRating]);

            // Notify idol about manual admin rating change
            if ($event === 'admin_manual') {
                $lockedUser->notify(new AdminRatingNotification($delta, $newRating, $note));
            }

            // If rating crossed the threshold downward, notify idol
            $threshold = (int) PlatformSetting::get('rating_low_threshold', 30);
            if ($oldRating >= $threshold && $newRating < $threshold) {
                $lockedUser->notify(new LowRatingWarningNotification($threshold));
            }

            // Sync updated rating back to original object
            $user->rating = $newRating;
        });
    }
}
