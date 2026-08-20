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
            [['ru' => 'Спорт',         'en' => 'Sports'], [
                ['ru' => 'Футбол',              'en' => 'Football'],
                ['ru' => 'Баскетбол',           'en' => 'Basketball'],
                ['ru' => 'Бег',                 'en' => 'Running'],
                ['ru' => 'Йога',                'en' => 'Yoga'],
                ['ru' => 'Плавание',            'en' => 'Swimming'],
                ['ru' => 'Велоспорт',           'en' => 'Cycling'],
                ['ru' => 'Хайкинг',             'en' => 'Hiking'],
                ['ru' => 'Фитнес',              'en' => 'Fitness'],
                ['ru' => 'Теннис',              'en' => 'Tennis'],
                ['ru' => 'Боевые искусства',    'en' => 'Martial Arts'],
            ]],
            [['ru' => 'Искусство',     'en' => 'Art'], [
                ['ru' => 'Рисование',   'en' => 'Drawing'],
                ['ru' => 'Фотография',  'en' => 'Photography'],
                ['ru' => 'Музыка',      'en' => 'Music'],
                ['ru' => 'Пение',       'en' => 'Singing'],
                ['ru' => 'Танцы',       'en' => 'Dancing'],
                ['ru' => 'Скульптура',  'en' => 'Sculpture'],
                ['ru' => 'Кино',        'en' => 'Cinema'],
                ['ru' => 'Театр',       'en' => 'Theatre'],
                ['ru' => 'Поэзия',      'en' => 'Poetry'],
            ]],
            [['ru' => 'Технологии',    'en' => 'Technology'], [
                ['ru' => 'Программирование',            'en' => 'Programming'],
                ['ru' => 'Искусственный интеллект',     'en' => 'Artificial Intelligence'],
                ['ru' => 'Видеоигры',                   'en' => 'Video Games'],
                ['ru' => 'Гаджеты',                     'en' => 'Gadgets'],
                ['ru' => 'Робототехника',               'en' => 'Robotics'],
                ['ru' => 'Блокчейн',                    'en' => 'Blockchain'],
            ]],
            [['ru' => 'Путешествия',   'en' => 'Travel'], [
                ['ru' => 'Туризм',              'en' => 'Tourism'],
                ['ru' => 'Бэкпекинг',           'en' => 'Backpacking'],
                ['ru' => 'Горный туризм',       'en' => 'Mountain Hiking'],
                ['ru' => 'Морские путешествия', 'en' => 'Sea Travel'],
                ['ru' => 'Городские прогулки',  'en' => 'City Walks'],
                ['ru' => 'Экзотические страны', 'en' => 'Exotic Countries'],
            ]],
            [['ru' => 'Кулинария',     'en' => 'Cooking'], [
                ['ru' => 'Готовка',     'en' => 'Cooking'],
                ['ru' => 'Кофе',        'en' => 'Coffee'],
                ['ru' => 'Вино',        'en' => 'Wine'],
                ['ru' => 'Стритфуд',    'en' => 'Street Food'],
                ['ru' => 'Выпечка',     'en' => 'Baking'],
                ['ru' => 'Барбекю',     'en' => 'Barbecue'],
            ]],
            [['ru' => 'Чтение',        'en' => 'Reading'], [
                ['ru' => 'Романы',      'en' => 'Novels'],
                ['ru' => 'Фантастика',  'en' => 'Sci-Fi & Fantasy'],
                ['ru' => 'Психология',  'en' => 'Psychology'],
                ['ru' => 'История',     'en' => 'History'],
                ['ru' => 'Биографии',   'en' => 'Biographies'],
                ['ru' => 'Философия',   'en' => 'Philosophy'],
            ]],
            [['ru' => 'Природа',       'en' => 'Nature'], [
                ['ru' => 'Кемпинг',         'en' => 'Camping'],
                ['ru' => 'Садоводство',     'en' => 'Gardening'],
                ['ru' => 'Животные',        'en' => 'Animals'],
                ['ru' => 'Птицы',           'en' => 'Birdwatching'],
                ['ru' => 'Звёздное небо',   'en' => 'Stargazing'],
            ]],
            [['ru' => 'Развлечения',   'en' => 'Entertainment'], [
                ['ru' => 'Настольные игры', 'en' => 'Board Games'],
                ['ru' => 'Сериалы',         'en' => 'TV Shows'],
                ['ru' => 'Стендап',         'en' => 'Stand-up Comedy'],
                ['ru' => 'Квизы',           'en' => 'Trivia'],
                ['ru' => 'Аниме',           'en' => 'Anime'],
            ]],
        ];

        InterestCategory::truncate();
        Interest::truncate();

        foreach ($data as $catIndex => [$catName, $interests]) {
            $category = InterestCategory::create([
                'name' => ['ru' => $catName['ru'], 'en' => $catName['en']],
                'sort_order' => $catIndex + 1,
            ]);

            foreach ($interests as $intIndex => $int) {
                Interest::create([
                    'category_id' => $category->id,
                    'name' => ['ru' => $int['ru'], 'en' => $int['en']],
                    'sort_order' => $intIndex + 1,
                ]);
            }
        }
    }
}
