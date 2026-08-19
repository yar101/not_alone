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
                'description'  => ['ru' => 'Начните лёгкий разговор на любые темы и просто приятно пообщайтесь.', 'en' => 'Start a light conversation on any topic and just enjoy each other\'s company.'],
                'name_suggestions' => ['ru' => ['Пообщаться в чате', 'Голосовой разговор', 'Видеозвонок на любые темы'], 'en' => ['Chill chat session', 'Casual voice call', 'Just talk about anything']],
            ],
            [
                'name'         => ['ru' => 'Беседа по интересам',    'en' => 'Interest-Based Chat'],
                'accent_color' => '#a0a0ff',
                'description'  => ['ru' => 'Поговорите о хобби, любимых занятиях и темах.', 'en' => 'Talk about hobbies, favourite activities and shared topics.'],
                'name_suggestions' => ['ru' => ['Поговорить об аниме', 'Обсудить любимые игры', 'Разговор о хобби по видеосвязи'], 'en' => ['Anime deep dive', 'Gaming chat & recommendations', 'Let\'s talk about our hobbies']],
            ],
            [
                'name'         => ['ru' => 'Поддержка',              'en' => 'Emotional Support'],
                'accent_color' => '#fef85a',
                'description'  => ['ru' => 'Поговорите с человеком, который внимательно выслушает и поддержит.', 'en' => 'Talk to someone who will genuinely listen and be there for you.'],
                'name_suggestions' => ['ru' => ['Поговорить и получить поддержку', 'Голосовой разговор по душам', 'Видеозвонок с поддержкой'], 'en' => ['I\'m here to listen', 'Heart-to-heart call', 'You\'re not alone — let\'s talk']],
            ],
            [
                'name'         => ['ru' => 'Совместный просмотр',    'en' => 'Watch Together'],
                'accent_color' => '#9726bc',
                'description'  => ['ru' => 'Включите фильм или видео и обсудите его во время просмотра.', 'en' => 'Put on a movie or video and discuss it as you watch together.'],
                'name_suggestions' => ['ru' => ['Посмотреть фильм вместе', 'Совместный просмотр аниме', 'Watch party'], 'en' => ['Movie night together', 'Anime watch-along', 'Watch party & live reactions']],
            ],
            [
                'name'         => ['ru' => 'Выговориться',           'en' => 'Vent Out'],
                'accent_color' => '#ed333b',
                'description'  => ['ru' => 'Поделитесь тем, что накопилось внутри, и получите спокойный отклик.', 'en' => 'Share what\'s been weighing on you and get a calm, caring response.'],
                'name_suggestions' => ['ru' => ['Выговориться в чате', 'Голосовой разговор по душам', 'Поделиться переживаниями'], 'en' => ['Vent session — I\'ll listen', 'Let it all out (voice call)', 'Safe space to share']],
            ],
            [
                'name'         => ['ru' => 'Рисуем вместе',          'en' => 'Draw Together'],
                'accent_color' => '#97d939',
                'description'  => ['ru' => 'Рисуйте вместе и делитесь идеями в процессе творчества.', 'en' => 'Draw together and share ideas in the creative process.'],
                'name_suggestions' => ['ru' => ['Порисовать вместе', 'Совместная арт-сессия', 'Нарисовать скетч вместе'], 'en' => ['Draw & doodle together', 'Collab art session', 'Sketch something together']],
            ],
            [
                'name'         => ['ru' => 'Караоке',                'en' => 'Karaoke'],
                'accent_color' => '#22e1bc',
                'description'  => ['ru' => 'Спойте любимые песни и почувствуйте атмосферу настоящего караоке.', 'en' => 'Sing your favourite songs and feel the vibe of a real karaoke session.'],
                'name_suggestions' => ['ru' => ['Спеть песни вместе', 'Караоке по видеосвязи', 'Дуэт любимой песни'], 'en' => ['Virtual karaoke night', 'Sing your fave songs with me', 'Duet session']],
            ],
            [
                'name'         => ['ru' => 'Совместная учёба',       'en' => 'Study Together'],
                'accent_color' => '#9268d0',
                'description'  => ['ru' => 'Позанимайтесь вместе, обсудите темы и получите помощь в обучении.', 'en' => 'Study side by side, discuss topics and help each other learn.'],
                'name_suggestions' => ['ru' => ['Совместная учебная сессия', 'Помощь с домашним заданием', 'Разобрать тему вместе'], 'en' => ['Study buddy session', 'Homework help & motivation', 'Explain a topic together']],
            ],
            [
                'name'         => ['ru' => 'Узнать судьбу',          'en' => 'Fortune Reading'],
                'accent_color' => '#740afa',
                'description'  => ['ru' => 'Задайте вопрос и узнайте, что может ждать вас впереди.', 'en' => 'Ask a question and discover what might lie ahead for you.'],
                'name_suggestions' => ['ru' => ['Расклад Таро на ситуацию', 'Ответ на личный вопрос', 'Предсказание на ближайшее будущее'], 'en' => ['Tarot reading for your situation', 'One question — one answer', 'What does the near future hold?']],
            ],
            [
                'name'         => ['ru' => 'Обмен контактами',       'en' => 'Exchange Contacts'],
                'accent_color' => '#18ca04',
                'description'  => ['ru' => 'Познакомьтесь и добавьте друг друга в соцсетях или мессенджерах.', 'en' => 'Get to know each other and connect on social media or messengers.'],
                'name_suggestions' => ['ru' => ['Добавить друг друга в соцсетях', 'Обменяться Instagram', 'Обменяться контактами'], 'en' => ['Connect on socials', 'Follow each other on Instagram', 'Stay in touch — let\'s connect']],
            ],
            [
                'name'         => ['ru' => 'Языковая практика',      'en' => 'Language Practice'],
                'accent_color' => '#ffffff',
                'description'  => ['ru' => 'Улучшайте иностранный язык через живое общение.', 'en' => 'Improve your foreign language skills through real conversation.'],
                'name_suggestions' => ['ru' => ['Практика разговорного английского', 'Разговорная практика японского', 'Живой разговор на иностранном языке'], 'en' => ['Spoken English practice', 'Japanese conversation practice', 'Live conversation in a foreign language']],
            ],
            [
                'name'         => ['ru' => 'День со мной',           'en' => 'Spend a Day with Me'],
                'accent_color' => '#da1b90',
                'description'  => ['ru' => 'Приятно проведите время вместе так, как пожелаете.', 'en' => 'Spend quality time together however you like.'],
                'name_suggestions' => ['ru' => ['Поиграть вместе онлайн', 'Провести вечер в разговоре', 'Просто пообщаться'], 'en' => ['Play games online together', 'Spend an evening chatting', 'Just hang out']],
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
