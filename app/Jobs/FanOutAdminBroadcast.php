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

    private function buildUserQuery(AdminBroadcast $broadcast)
    {
        if ($broadcast->target === 'user') {
            return User::where('id', $broadcast->target_user_id);
        }

        $query = User::query();

        if ($broadcast->target === 'all') {
            return $query;
        }

        // target === 'filtered'
        $filters = $broadcast->target_filters ?? [];

        if (isset($filters['is_idol'])) {
            $query->where('is_idol', $filters['is_idol'] === '1');
        }

        if (isset($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (isset($filters['age_from']) || isset($filters['age_to'])) {
            $query->whereNotNull('birth_date');

            if (isset($filters['age_from'])) {
                $query->whereDate('birth_date', '<=', now()->subYears((int) $filters['age_from'])->toDateString());
            }

            if (isset($filters['age_to'])) {
                $query->whereDate('birth_date', '>=', now()->subYears((int) $filters['age_to'] + 1)->addDay()->toDateString());
            }
        }

        if (isset($filters['registered_from'])) {
            $query->whereDate('created_at', '>=', $filters['registered_from']);
        }

        if (isset($filters['registered_to'])) {
            $query->whereDate('created_at', '<=', $filters['registered_to']);
        }

        return $query;
    }
}
