<?php

namespace App\Jobs;

use App\Events\NewNotification;
use App\Models\AdminBroadcast;
use App\Models\User;
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FanOutAdminBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private AdminBroadcast $broadcast) {}

    public function handle(): void
    {
        $broadcast = $this->broadcast;

        $this->buildUserQuery($broadcast)->chunkById(100, function ($users) use ($broadcast) {
            foreach ($users as $user) {
                $alreadyNotified = $user->notifications()
                    ->whereRaw("(data::jsonb->>'broadcast_id')::int = ?", [$broadcast->id])
                    ->exists();

                if ($alreadyNotified) {
                    continue;
                }

                $user->notify(new AdminBroadcastNotification($broadcast));
            }
        });

        if ($broadcast->target === 'user') {
            broadcast(new NewNotification('private', (int) $broadcast->target_user_id));
        } else {
            broadcast(new NewNotification('public'));
        }
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
                $query->whereRaw(
                    "DATE_PART('year', AGE(birth_date::date)) >= ?",
                    [(int) $filters['age_from']]
                );
            }

            if (isset($filters['age_to'])) {
                $query->whereRaw(
                    "DATE_PART('year', AGE(birth_date::date)) <= ?",
                    [(int) $filters['age_to']]
                );
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
