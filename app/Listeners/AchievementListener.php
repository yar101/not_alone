<?php

namespace App\Listeners;

use App\Events\OrderCompletedEvent;
use App\Models\User;
use App\Services\AvatarFrames\AchievementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class AchievementListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected ?AchievementService $achievementService = null
    ) {
        $this->achievementService = $this->achievementService ?? app(AchievementService::class);
    }

    public function handle(OrderCompletedEvent $event): void
    {
        if ($event->order->idol) {
            $this->evaluateUser($event->order->idol);
        }
        if ($event->order->customer) {
            $this->evaluateUser($event->order->customer);
        }
    }

    public function evaluateUser(?User $user): void
    {
        $this->achievementService->evaluateUser($user);
    }
}
