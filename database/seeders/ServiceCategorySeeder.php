<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        ServiceCategory::truncate();

        $categories = [
            [
                'name'         => ['ru' => 'Непринуждённая беседа',  'en' => 'Casual Chat'],
                'accent_color' => '#dc8add',
                'image_path'   => 'cat_images/cat_star_nepr_beseda.png',
                'description'  => ['ru' => 'Начните лёгкий разговор на любые темы и просто приятно пообщайтесь.', 'en' => 'Start a light conversation on any topic and just enjoy each other\'s company.'],
                'name_suggestions' => ['Пообщаться в чате', 'Голосовой разговор', 'Видеозвонок на любые темы'],
            ],
            [
                'name'         => ['ru' => 'Беседа по интересам',    'en' => 'Interest-Based Chat'],
                'accent_color' => '#a0a0ff',
                'image_path'   => 'cat_images/cat_star_beseda_po_inter.png',
                'description'  => ['ru' => 'Поговорите о хобби, любимых занятиях и темах.', 'en' => 'Talk about hobbies, favourite activities and shared topics.'],
                'name_suggestions' => ['Поговорить об аниме', 'Обсудить любимые игры', 'Разговор о хобби по видеосвязи'],
            ],
            [
                'name'         => ['ru' => 'Поддержка',              'en' => 'Emotional Support'],
                'accent_color' => '#fef85a',
                'image_path'   => 'cat_images/cat_star_podderzka.png',
                'description'  => ['ru' => 'Поговорите с человеком, который внимательно выслушает и поддержит.', 'en' => 'Talk to someone who will genuinely listen and be there for you.'],
                'name_suggestions' => ['Поговорить и получить поддержку', 'Голосовой разговор по душам', 'Видеозвонок с поддержкой'],
            ],
            [
                'name'         => ['ru' => 'Совместный просмотр',    'en' => 'Watch Together'],
                'accent_color' => '#9726bc',
                'image_path'   => 'cat_images/cat_star_sovm_prosmotr.png',
                'description'  => ['ru' => 'Включите фильм или видео и обсудите его во время просмотра.', 'en' => 'Put on a movie or video and discuss it as you watch together.'],
                'name_suggestions' => ['Посмотреть фильм вместе', 'Совместный просмотр аниме', 'Watch party'],
            ],
            [
                'name'         => ['ru' => 'Выговориться',           'en' => 'Vent Out'],
                'accent_color' => '#ed333b',
                'image_path'   => 'cat_images/cat_star_vigovoritsa.png',
                'description'  => ['ru' => 'Поделитесь тем, что накопилось внутри, и получите спокойный отклик.', 'en' => 'Share what\'s been weighing on you and get a calm, caring response.'],
                'name_suggestions' => ['Выговориться в чате', 'Голосовой разговор по душам', 'Поделиться переживаниями'],
            ],
            [
                'name'         => ['ru' => 'Рисуем вместе',          'en' => 'Draw Together'],
                'accent_color' => '#97d939',
                'image_path'   => 'cat_images/cat_star_risuem_vmeste.png',
                'description'  => ['ru' => 'Рисуйте вместе и делитесь идеями в процессе творчества.', 'en' => 'Draw together and share ideas in the creative process.'],
                'name_suggestions' => ['Порисовать вместе', 'Совместная арт-сессия', 'Нарисовать скетч вместе'],
            ],
            [
                'name'         => ['ru' => 'Караоке',                'en' => 'Karaoke'],
                'accent_color' => '#22e1bc',
                'image_path'   => 'cat_images/cat_star_karaoke.png',
                'description'  => ['ru' => 'Спойте любимые песни и почувствуйте атмосферу настоящего караоке.', 'en' => 'Sing your favourite songs and feel the vibe of a real karaoke session.'],
                'name_suggestions' => ['Спеть песни вместе', 'Караоке по видеосвязи', 'Дуэт любимой песни'],
            ],
            [
                'name'         => ['ru' => 'Совместная учёба',       'en' => 'Study Together'],
                'accent_color' => '#9268d0',
                'image_path'   => 'cat_images/cat_star_sovm_ucheba.png',
                'description'  => ['ru' => 'Позанимайтесь вместе, обсудите темы и получите помощь в обучении.', 'en' => 'Study side by side, discuss topics and help each other learn.'],
                'name_suggestions' => ['Совместная учебная сессия', 'Помощь с домашним заданием', 'Разобрать тему вместе'],
            ],
            [
                'name'         => ['ru' => 'Узнать судьбу',          'en' => 'Fortune Reading'],
                'accent_color' => '#740afa',
                'image_path'   => 'cat_images/cat_star_uznat_sudbu.png',
                'description'  => ['ru' => 'Задайте вопрос и узнайте, что может ждать вас впереди.', 'en' => 'Ask a question and discover what might lie ahead for you.'],
                'name_suggestions' => ['Расклад Таро на ситуацию', 'Ответ на личный вопрос', 'Предсказание на ближайшее будущее'],
            ],
            [
                'name'         => ['ru' => 'Обмен контактами',       'en' => 'Exchange Contacts'],
                'accent_color' => '#18ca04',
                'image_path'   => 'cat_images/cat_star_obmen_contactami.png',
                'description'  => ['ru' => 'Познакомьтесь и добавьте друг друга в соцсетях или мессенджерах.', 'en' => 'Get to know each other and connect on social media or messengers.'],
                'name_suggestions' => ['Добавить друг друга в соцсетях', 'Обменяться Instagram', 'Обменяться контактами'],
            ],
            [
                'name'         => ['ru' => 'Языковая практика',      'en' => 'Language Practice'],
                'accent_color' => '#ffffff',
                'image_path'   => 'cat_images/cat_star_yazikovaya_practica.png',
                'description'  => ['ru' => 'Улучшайте иностранный язык через живое общение.', 'en' => 'Improve your foreign language skills through real conversation.'],
                'name_suggestions' => ['Практика разговорного английского', 'Разговорная практика японского', 'Живой разговор на иностранном языке'],
            ],
            [
                'name'         => ['ru' => 'День со мной',           'en' => 'Spend a Day with Me'],
                'accent_color' => '#da1b90',
                'image_path'   => 'cat_images/cat_star_den_so_mnoy.png',
                'description'  => ['ru' => 'Приятно проведите время вместе так, как пожелаете.', 'en' => 'Spend quality time together however you like.'],
                'name_suggestions' => ['Поиграть вместе онлайн', 'Провести вечер в разговоре', 'Просто пообщаться'],
            ],
        ];

        foreach ($categories as $i => $cat) {
            ServiceCategory::create([
                'name'             => $cat['name'],
                'accent_color'     => $cat['accent_color'],
                'image_path'       => $cat['image_path'] ?? null,
                'description'      => $cat['description'],
                'name_suggestions' => $cat['name_suggestions'],
                'sort_order'       => $i,
                'is_active'        => true,
            ]);
        }
    }
}
