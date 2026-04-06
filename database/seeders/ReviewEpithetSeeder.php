<?php

namespace Database\Seeders;

use App\Models\ReviewEpithet;
use Illuminate\Database\Seeder;

class ReviewEpithetSeeder extends Seeder
{
    public function run(): void
    {
        $labels = [
            'Доброжелательность',
            'Позитивность',
            'Приветливость',
            'Разговорчивость',
            'Умение поддержать',
            'Чувство юмора',
            'Пунктуальность',
            'Отзывчивость',
            'Терпеливость',
            'Креативность',
            'Профессионализм',
            'Вовлечённость',
        ];

        foreach ($labels as $i => $label) {
            ReviewEpithet::firstOrCreate(
                ['label' => $label],
                ['sort_order' => $i]
            );
        }
    }
}
