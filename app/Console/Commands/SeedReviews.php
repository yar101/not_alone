<?php

namespace App\Console\Commands;

use App\Models\Review;
use App\Models\ReviewEpithet;
use App\Models\Service;
use App\Models\User;
use Illuminate\Console\Command;

class SeedReviews extends Command
{
    protected $signature = 'reviews:seed {idolId} {count=5}';

    protected $description = 'Генерирует тестовые отзывы для указанного айдола';

    public function handle(): void
    {
        $idolId = $this->argument('idolId');
        $count  = (int) $this->argument('count');

        $idol = User::find($idolId);

        if (!$idol) {
            $this->error("Пользователь с ID {$idolId} не найден.");
            return;
        }

        if (!$idol->is_idol) {
            $this->error("Пользователь «{$idol->name}» (ID {$idolId}) не является айдолом.");
            return;
        }

        $this->line('');
        $this->line("  Айдол : <fg=cyan>{$idol->name}</>");
        $this->line("  Email : {$idol->email}");
        $this->line("  ID    : {$idol->id}");
        $this->line("  Отзывов сейчас: " . Review::where('idol_id', $idol->id)->count());
        $this->line('');

        if (!$this->confirm("Создать {$count} отзыв(ов)?")) {
            $this->line('Отменено.');
            return;
        }

        $epithets = ReviewEpithet::withoutGlobalScopes()->pluck('id')->toArray();

        $services = Service::where('user_id', $idol->id)
            ->with(['category', 'timeUnit'])
            ->get();

        $fakeSnapshot = $services->isNotEmpty()
            ? $services->take(rand(1, min(3, $services->count())))->map(fn($s) => [
                'name'          => $s->name,
                'category_name' => $s->category?->name,
                'price'         => $s->price,
                'time_unit'     => $s->timeUnit?->name,
                'quantity'      => 1,
            ])->values()->all()
            : [['name' => 'Общение', 'category_name' => 'Разное', 'price' => 500, 'time_unit' => '30 мин', 'quantity' => 1]];

        $fakePhrases = [
            'Очень понравилось общение, всё прошло отлично!',
            'Приятный человек, рекомендую.',
            'Всё чётко, никаких нареканий.',
            'Было здорово, обязательно вернусь.',
            'Отличный опыт, спасибо большое!',
            'Немного задержались, но в целом всё хорошо.',
            'Супер, всё как договаривались.',
            null,
            null,
        ];

        // Get users who haven't reviewed this idol yet
        $usedReviewerIds = Review::where('idol_id', $idol->id)->pluck('reviewer_id')->toArray();
        $candidates = User::where('id', '!=', $idol->id)
            ->whereNotIn('id', $usedReviewerIds)
            ->inRandomOrder()
            ->limit($count)
            ->get();

        if ($candidates->isEmpty()) {
            $this->error('Нет доступных пользователей для создания отзывов (все уже оставили отзыв или пользователей нет).');
            return;
        }

        if ($candidates->count() < $count) {
            $this->warn("Доступно только {$candidates->count()} уникальных рецензентов, создаём столько.");
            $count = $candidates->count();
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($candidates->take($count) as $reviewer) {
            $selectedEpithets = $epithets
                ? array_slice(array_values(array_unique(
                    array_map(fn() => $epithets[array_rand($epithets)], range(0, rand(1, 4)))
                )), 0, rand(2, 5))
                : [];

            $createdAt = now()->subDays(rand(0, 365))->subHours(rand(0, 23))->subMinutes(rand(0, 59));

            $review = Review::create([
                'reviewer_id'      => $reviewer->id,
                'idol_id'          => $idol->id,
                'order_id'         => null,
                'rating'           => rand(3, 5),
                'text'             => $fakePhrases[array_rand($fakePhrases)],
                'services_snapshot'=> $fakeSnapshot,
                'created_at'       => $createdAt,
                'updated_at'       => $createdAt,
            ]);

            if ($selectedEpithets) {
                $review->epithets()->sync($selectedEpithets);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Готово. Всего отзывов у айдола: " . Review::where('idol_id', $idol->id)->count());
    }
}
