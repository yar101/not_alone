<?php

namespace App\Jobs;

use App\Events\NewNotification;
use App\Models\AdminBroadcast;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use App\Traits\SafeBroadcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanOutAdminBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SafeBroadcast, SerializesModels;

    public function __construct(private AdminBroadcast $broadcast) {}

    public function handle(): void
    {
        $broadcast = $this->broadcast;

        if ($broadcast->target === 'user') {
            $user = User::find($broadcast->target_user_id);
            if ($user) {
                $alreadyNotified = $user->notifications()
                    ->where(function ($q) use ($broadcast) {
                        $q->where('data', 'like', '%"broadcast_id":'.$broadcast->id.'%')
                            ->orWhere('data', 'like', '%"broadcast_id":"'.$broadcast->id.'"%');
                    })
                    ->exists();

                if (! $alreadyNotified) {
                    $user->notify(new AdminBroadcastNotification($broadcast));
                }
            }
            $this->safeBroadcast(new NewNotification('private', (int) $broadcast->target_user_id));

            return;
        }

        $this->safeBroadcast(new NewNotification('public'));
    }
}
