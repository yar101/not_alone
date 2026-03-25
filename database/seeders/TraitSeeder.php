<?php

namespace Database\Seeders;

use App\Models\PersonalityTrait;
use Illuminate\Database\Seeder;

class TraitSeeder extends Seeder
{
    public function run(): void
    {
        $traits = [
            'Добрый', 'Умный', 'Весёлый', 'Честный', 'Ответственный',
            'Творческий', 'Смелый', 'Нежный', 'Терпеливый', 'Оптимистичный',
            'Общительный', 'Романтичный', 'Независимый', 'Надёжный', 'Амбициозный',
            'Харизматичный', 'Любознательный', 'Эмпатичный', 'Игривый', 'Прямолинейный',
            'Заботливый', 'Мечтательный', 'Серьёзный', 'Открытый', 'Скромный',
            'Авантюрный', 'Аналитический', 'Энергичный', 'Спокойный', 'Щедрый',
        ];

        foreach ($traits as $index => $name) {
            PersonalityTrait::firstOrCreate(
                ['name_ru' => $name],
                ['sort_order' => $index]
            );
        }
    }
}
