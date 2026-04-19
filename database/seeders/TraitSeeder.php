<?php

namespace Database\Seeders;

use App\Models\PersonalityTrait;
use Illuminate\Database\Seeder;

class TraitSeeder extends Seeder
{
    public function run(): void
    {
        $traits = [
            ['ru' => 'Добрый',          'en' => 'Kind'],
            ['ru' => 'Умный',           'en' => 'Intelligent'],
            ['ru' => 'Весёлый',         'en' => 'Cheerful'],
            ['ru' => 'Честный',         'en' => 'Honest'],
            ['ru' => 'Ответственный',   'en' => 'Responsible'],
            ['ru' => 'Творческий',      'en' => 'Creative'],
            ['ru' => 'Смелый',          'en' => 'Brave'],
            ['ru' => 'Нежный',          'en' => 'Gentle'],
            ['ru' => 'Терпеливый',      'en' => 'Patient'],
            ['ru' => 'Оптимистичный',   'en' => 'Optimistic'],
            ['ru' => 'Общительный',     'en' => 'Sociable'],
            ['ru' => 'Романтичный',     'en' => 'Romantic'],
            ['ru' => 'Независимый',     'en' => 'Independent'],
            ['ru' => 'Надёжный',        'en' => 'Reliable'],
            ['ru' => 'Амбициозный',     'en' => 'Ambitious'],
            ['ru' => 'Харизматичный',   'en' => 'Charismatic'],
            ['ru' => 'Любознательный',  'en' => 'Curious'],
            ['ru' => 'Эмпатичный',      'en' => 'Empathetic'],
            ['ru' => 'Игривый',         'en' => 'Playful'],
            ['ru' => 'Прямолинейный',   'en' => 'Straightforward'],
            ['ru' => 'Заботливый',      'en' => 'Caring'],
            ['ru' => 'Мечтательный',    'en' => 'Dreamy'],
            ['ru' => 'Серьёзный',       'en' => 'Serious'],
            ['ru' => 'Открытый',        'en' => 'Open-minded'],
            ['ru' => 'Скромный',        'en' => 'Humble'],
            ['ru' => 'Авантюрный',      'en' => 'Adventurous'],
            ['ru' => 'Аналитический',   'en' => 'Analytical'],
            ['ru' => 'Энергичный',      'en' => 'Energetic'],
            ['ru' => 'Спокойный',       'en' => 'Calm'],
            ['ru' => 'Щедрый',          'en' => 'Generous'],
        ];

        PersonalityTrait::truncate();

        foreach ($traits as $index => $t) {
            PersonalityTrait::create([
                'name'       => ['ru' => $t['ru'], 'en' => $t['en']],
                'sort_order' => $index,
            ]);
        }
    }
}
