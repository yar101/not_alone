<?php

namespace App\Listeners;

use App\Events\ReviewCreatedEvent;
use App\Services\AvatarFrames\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ReviewAchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ?AchievementService $achievementService = null
    ) {
        $this->achievementService = $this->achievementService ?? app(AchievementService::class);
    }

    public function handle(ReviewCreatedEvent $event): void
    {
        $this->achievementService->evaluateUser($event->review->idol);
    }
}
