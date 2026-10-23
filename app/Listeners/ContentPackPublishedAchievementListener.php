<?php

namespace App\Listeners;

use App\Events\ContentPackPublishedEvent;
use App\Services\AvatarFrames\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ContentPackPublishedAchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ?AchievementService $achievementService = null
    ) {
        $this->achievementService = $this->achievementService ?? app(AchievementService::class);
    }

    public function handle(ContentPackPublishedEvent $event): void
    {
        $this->achievementService->evaluateUser($event->pack->user);
    }
}
