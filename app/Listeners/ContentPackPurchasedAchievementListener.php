<?php

namespace App\Listeners;

use App\Events\ContentPackPurchasedEvent;
use App\Services\AvatarFrames\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ContentPackPurchasedAchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ?AchievementService $achievementService = null
    ) {
        $this->achievementService = $this->achievementService ?? app(AchievementService::class);
    }

    public function handle(ContentPackPurchasedEvent $event): void
    {
        $this->achievementService->evaluateUser($event->purchase->contentPack?->user);
    }
}
