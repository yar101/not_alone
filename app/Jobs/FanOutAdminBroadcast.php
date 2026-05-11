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

        // Для персональной рассылки (один пользователь) продолжаем использовать Fan-out,
        // так как это удобно для пуш-уведомлений и личной истории.
        if ($broadcast->target === 'user') {
            $user = User::find($broadcast->target_user_id);
            if ($user) {
                $alreadyNotified = $user->notifications()
                    ->whereRaw("(data::jsonb->>'broadcast_id')::int = ?", [$broadcast->id])
                    ->exists();

                if (!$alreadyNotified) {
                    $user->notify(new AdminBroadcastNotification($broadcast));
                }
            }
            broadcast(new NewNotification('private', (int) $broadcast->target_user_id));
            return;
        }

        // Для массовых рассылок (все или фильтр) мы больше не создаем тысячи записей в БД.
        // Мы просто сигнализируем по вебсокету, что есть «что-то новое» для всех.
        broadcast(new NewNotification('public'));
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
