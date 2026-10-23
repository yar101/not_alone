<?php

namespace App\Listeners;

use App\Events\UserFollowedEvent;
use App\Services\AvatarFrames\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class FollowAchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ?AchievementService $achievementService = null
    ) {
        $this->achievementService = $this->achievementService ?? app(AchievementService::class);
    }

    public function handle(UserFollowedEvent $event): void
    {
        $this->achievementService->evaluateUser($event->idol);
    }
}
