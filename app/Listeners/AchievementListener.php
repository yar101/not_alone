<?php

namespace App\Listeners;

use App\Events\OrderCompletedEvent;
use App\Models\AvatarFrame;
use App\Models\User;
use App\Services\AvatarFrames\ConditionRegistry;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(OrderCompletedEvent $event): void
    {
        $this->evaluateUser($event->order->idol);
        $this->evaluateUser($event->order->customer);
    }

    private function evaluateUser(User $user): void
    {
        $unlockedFrameIds = $user->avatarFrames()->pluck('avatar_frames.id')->toArray();

        $potentialFrames = AvatarFrame::where('is_active', true)
            ->where('type', 'achievement')
            ->whereNotNull('condition_class')
            ->whereNotIn('id', $unlockedFrameIds)
            ->get();

        foreach ($potentialFrames as $frame) {
            $condition = ConditionRegistry::get($frame->condition_class);
            
            if ($condition && $condition->check($user)) {
                $user->avatarFrames()->attach($frame->id);

                Log::info("User {$user->id} unlocked frame {$frame->id} ({$frame->name})");
                
                // TODO: Dispatch a real-time event/notification for UI popup
            }
        }
    }
}
