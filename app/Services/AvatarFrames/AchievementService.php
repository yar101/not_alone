<?php

namespace App\Services\AvatarFrames;

use App\Models\AvatarFrame;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AchievementService
{
    public function evaluateUser(?User $user): void
    {
        if (! $user) {
            return;
        }

        $unlockedFrameIds = $user->avatarFrames()->pluck('avatar_frames.id')->toArray();

        $potentialFrames = AvatarFrame::where('is_active', true)
            ->where('type', 'achievement')
            ->whereNotNull('condition_class')
            ->whereNotIn('id', $unlockedFrameIds)
            ->get();

        foreach ($potentialFrames as $frame) {
            $condition = ConditionRegistry::get($frame->condition_class);

            if (! $condition) {
                continue;
            }

            try {
                if ($condition->check($user)) {
                    $user->avatarFrames()->syncWithoutDetaching([$frame->id]);

                    Log::info("User {$user->id} unlocked frame {$frame->id} ({$frame->name})");

                    // TODO: Dispatch a real-time event/notification for UI popup
                }
            } catch (\Throwable $e) {
                Log::warning("Achievement condition check failed for user {$user->id}, frame {$frame->id}: {$e->getMessage()}");
            }
        }
    }
}
