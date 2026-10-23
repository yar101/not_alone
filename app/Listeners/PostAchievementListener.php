<?php

namespace App\Listeners;

use App\Events\PostCreatedEvent;
use App\Services\AvatarFrames\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class PostAchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ?AchievementService $achievementService = null
    ) {
        $this->achievementService = $this->achievementService ?? app(AchievementService::class);
    }

    public function handle(PostCreatedEvent $event): void
    {
        $this->achievementService->evaluateUser($event->post->user);
    }
}
