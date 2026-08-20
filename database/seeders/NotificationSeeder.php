<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $user = $this->resolveUser();

        if (! $user) {
            $this->command->error('Нет ни одного пользователя. Сначала создайте пользователя.');

            return;
        }

        $count = (int) $this->command->ask('Сколько уведомлений создать?', 30);

        $this->command->info("Создаём {$count} уведомлений для {$user->email}...");

        $types = [
            ['type' => 'order_created',           'data' => fn () => ['type' => 'order_created',           'order_id' => fake()->numberBetween(1, 999), 'customer_name' => fake()->name()]],
            ['type' => 'order_accepted',          'data' => fn () => ['type' => 'order_accepted',          'order_id' => fake()->numberBetween(1, 999), 'idol_name' => fake()->name(), 'idol_gender' => fake()->randomElement(['male', 'female'])]],
            ['type' => 'order_cancelled',         'data' => fn () => ['type' => 'order_cancelled',         'order_id' => fake()->numberBetween(1, 999)]],
            ['type' => 'order_paid',              'data' => fn () => ['type' => 'order_paid',              'order_id' => fake()->numberBetween(1, 999), 'customer_name' => fake()->name(), 'customer_gender' => fake()->randomElement(['male', 'female'])]],
            ['type' => 'order_completed',         'data' => fn () => ['type' => 'order_completed',         'order_id' => fake()->numberBetween(1, 999)]],
            ['type' => 'idol_approved',           'data' => fn () => ['type' => 'idol_approved',           'title' => 'Заявка одобрена',  'message' => 'Ваша заявка на статус айдола одобрена.']],
            ['type' => 'idol_rejected',           'data' => fn () => ['type' => 'idol_rejected',           'title' => 'Заявка отклонена', 'message' => 'Ваша заявка отклонена. Попробуйте позже.']],
            ['type' => 'admin_broadcast',         'data' => fn () => ['type' => 'admin_broadcast',         'title' => 'Объявление',       'message' => fake()->sentence()]],
            ['type' => 'low_rating_warning',      'data' => fn () => ['type' => 'low_rating_warning',      'title' => 'Предупреждение',   'message' => 'Ваш рейтинг упал ниже допустимого уровня.']],
            ['type' => 'content_pack_approved',   'data' => fn () => ['type' => 'content_pack_approved',   'title' => 'Пак одобрен',      'message' => 'Ваш контент-пак прошёл модерацию.']],
            ['type' => 'content_pack_remarks',    'data' => fn () => ['type' => 'content_pack_remarks',    'title' => 'Замечания к паку', 'message' => 'Требуются исправления.', 'reason' => fake()->sentence()]],
            ['type' => 'content_pack_rejected',   'data' => fn () => ['type' => 'content_pack_rejected',   'title' => 'Пак отклонён',     'message' => 'Пак не соответствует правилам.']],
            ['type' => 'review_dispute_approved', 'data' => fn () => ['type' => 'review_dispute_approved', 'title' => 'Спор одобрен',     'message' => 'Ваш спор по отзыву рассмотрен в вашу пользу.']],
            ['type' => 'review_dispute_rejected', 'data' => fn () => ['type' => 'review_dispute_rejected', 'title' => 'Спор отклонён',    'message' => 'Ваш спор по отзыву не удовлетворён.']],
            ['type' => 'new_review',              'data' => fn () => ['type' => 'new_review',              'title' => 'Новый отзыв',      'message' => 'Вам оставили новый отзыв.']],
        ];

        $rows = [];
        $now = now();

        for ($i = 0; $i < $count; $i++) {
            $tpl = fake()->randomElement($types);
            $data = ($tpl['data'])();
            $ts = $now->copy()->subMinutes($i * fake()->numberBetween(10, 120));
            $rows[] = [
                'id' => Str::uuid()->toString(),
                'type' => 'App\Notifications\GeneralNotification',
                'notifiable_type' => 'App\Models\User',
                'notifiable_id' => $user->id,
                'data' => json_encode($data),
                'read_at' => fake()->boolean(40) ? $ts->copy()->addMinutes(5) : null,
                'created_at' => $ts,
                'updated_at' => $ts,
            ];
        }

        DB::table('notifications')->insert($rows);

        $this->command->info("Готово: создано {$count} уведомлений.");
    }

    private function resolveUser(): ?User
    {
        $users = User::orderBy('id')->get(['id', 'email', 'name']);

        if ($users->isEmpty()) {
            return null;
        }
        if ($users->count() === 1) {
            return $users->first();
        }

        $choices = $users->mapWithKeys(fn ($u) => [$u->email => "{$u->name} ({$u->email})"])->toArray();
        $choice = $this->command->choice('Для какого пользователя?', array_values($choices));
        $email = array_search($choice, $choices);

        return $users->firstWhere('email', $email);
    }
}
