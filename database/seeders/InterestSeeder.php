<?php

namespace Database\Seeders;

use App\Models\Interest;
use App\Models\InterestCategory;
use Illuminate\Database\Seeder;

class InterestSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            ['Спорт', [
                'Футбол', 'Баскетбол', 'Бег', 'Йога', 'Плавание',
                'Велоспорт', 'Хайкинг', 'Фитнес', 'Теннис', 'Боевые искусства',
            ]],
            ['Искусство', [
                'Рисование', 'Фотография', 'Музыка', 'Пение', 'Танцы',
                'Скульптура', 'Кино', 'Театр', 'Поэзия',
            ]],
            ['Технологии', [
                'Программирование', 'Искусственный интеллект', 'Видеоигры',
                'Гаджеты', 'Робототехника', 'Блокчейн',
            ]],
            ['Путешествия', [
                'Туризм', 'Бэкпекинг', 'Горный туризм', 'Морские путешествия',
                'Городские прогулки', 'Экзотические страны',
            ]],
            ['Кулинария', [
                'Готовка', 'Кофе', 'Вино', 'Стритфуд', 'Выпечка', 'Барбекю',
            ]],
            ['Чтение', [
                'Романы', 'Фантастика', 'Психология', 'История',
                'Биографии', 'Философия',
            ]],
            ['Природа', [
                'Кемпинг', 'Садоводство', 'Животные', 'Птицы', 'Звёздное небо',
            ]],
            ['Развлечения', [
                'Настольные игры', 'Сериалы', 'Стендап', 'Квизы', 'Аниме',
            ]],
        ];

        foreach ($data as $catIndex => [$catName, $interests]) {
            $category = InterestCategory::firstOrCreate(
                ['name_ru' => $catName],
                ['sort_order' => $catIndex + 1]
            );

            foreach ($interests as $intIndex => $intName) {
                Interest::firstOrCreate(
                    ['category_id' => $category->id, 'name_ru' => $intName],
                    ['sort_order' => $intIndex + 1]
                );
            }
        }
    }
}
