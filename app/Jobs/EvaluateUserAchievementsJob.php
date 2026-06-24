<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\AvatarFrame;
use App\Services\AvatarFrames\ConditionRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EvaluateUserAchievementsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user)
    {
    }

    public function handle(): void
    {
        // Получаем ID рамок, которые у юзера уже есть
        $unlockedFrameIds = $this->user->avatarFrames()->pluck('avatar_frames.id')->toArray();

        // Ищем активные рамки-ачивки, которых у него еще нет
        $potentialFrames = AvatarFrame::where('is_active', true)
            ->where('type', 'achievement')
            ->whereNotNull('condition_class')
            ->whereNotIn('id', $unlockedFrameIds)
            ->get();

        foreach ($potentialFrames as $frame) {
            $condition = ConditionRegistry::get($frame->condition_class);
            
            if ($condition && $condition->check($this->user)) {
                // Выдаем рамку
                $this->user->avatarFrames()->attach($frame->id);

                Log::info("User {$this->user->id} unlocked frame {$frame->id} ({$frame->name})");

                // TODO: Отправить системное уведомление на сайте (через Notification)
            }
        }
    }
}
