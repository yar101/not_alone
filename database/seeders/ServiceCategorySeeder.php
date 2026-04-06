<?php

namespace Database\Seeders;

use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;

class ServiceCategorySeeder extends Seeder
{
    public function run(): void
    {
        ServiceCategory::truncate();

        // Порядок и цвета зафиксированы из БД (sort_order 0–12)
        $categories = [
            [
                'name'         => 'Непринуждённая беседа',
                'accent_color' => '#dc8add',
                'image_path'   => 'cat_images/cat_star_nepr_beseda.png',
                'description'  => 'Начните лёгкий разговор на любые темы и просто приятно пообщайтесь.',
                'name_suggestions' => [
                    'Пообщаться в чате',
                    'Голосовой разговор',
                    'Видеозвонок на любые темы',
                ],
            ],
            [
                'name'         => 'Беседа по интересам',
                'accent_color' => '#a0a0ff',
                'image_path'   => 'cat_images/cat_star_beseda_po_inter.png',
                'description'  => 'Поговорите о хобби, любимых занятиях и темах.',
                'name_suggestions' => [
                    'Поговорить об аниме',
                    'Обсудить любимые игры',
                    'Разговор о хобби по видеосвязи',
                ],
            ],
            [
                'name'         => 'Поддержка',
                'accent_color' => '#fef85a',
                'image_path'   => 'cat_images/cat_star_podderzka.png',
                'description'  => 'Поговорите с человеком, который внимательно выслушает и поддержит.',
                'name_suggestions' => [
                    'Поговорить и получить поддержку',
                    'Голосовой разговор по душам',
                    'Видеозвонок с поддержкой',
                ],
            ],
            [
                'name'         => 'Совместный просмотр',
                'accent_color' => '#9726bc',
                'image_path'   => 'cat_images/cat_star_sovm_prosmotr.png',
                'description'  => 'Включите фильм или видео и обсудите его во время просмотра.',
                'name_suggestions' => [
                    'Посмотреть фильм вместе',
                    'Совместный просмотр аниме',
                    'Watch party',
                ],
            ],
            [
                'name'         => 'Выговориться',
                'accent_color' => '#ed333b',
                'image_path'   => 'cat_images/cat_star_vigovoritsa.png',
                'description'  => 'Поделитесь тем, что накопилось внутри, и получите спокойный отклик.',
                'name_suggestions' => [
                    'Выговориться в чате',
                    'Голосовой разговор по душам',
                    'Поделиться переживаниями',
                ],
            ],
            [
                'name'         => 'Рисуем вместе',
                'accent_color' => '#97d939',
                'image_path'   => 'cat_images/cat_star_risuem_vmeste.png',
                'description'  => 'Рисуйте вместе и делитесь идеями в процессе творчества.',
                'name_suggestions' => [
                    'Порисовать вместе',
                    'Совместная арт-сессия',
                    'Нарисовать скетч вместе',
                ],
            ],
            [
                'name'         => 'Караоке',
                'accent_color' => '#22e1bc',
                'image_path'   => 'cat_images/cat_star_karaoke.png',
                'description'  => 'Спойте любимые песни и почувствуйте атмосферу настоящего караоке.',
                'name_suggestions' => [
                    'Спеть песни вместе',
                    'Караоке по видеосвязи',
                    'Дуэт любимой песни',
                ],
            ],
            [
                'name'         => 'Совместная учёба',
                'accent_color' => '#9268d0',
                'image_path'   => 'cat_images/cat_star_sovm_ucheba.png',
                'description'  => 'Позанимайтесь вместе, обсудите темы и получите помощь в обучении.',
                'name_suggestions' => [
                    'Совместная учебная сессия',
                    'Помощь с домашним заданием',
                    'Разобрать тему вместе',
                ],
            ],
            [
                'name'         => 'Узнать судьбу',
                'accent_color' => '#740afa',
                'image_path'   => 'cat_images/cat_star_uznat_sudbu.png',
                'description'  => 'Задайте вопрос и узнайте, что может ждать вас впереди.',
                'name_suggestions' => [
                    'Расклад Таро на ситуацию',
                    'Ответ на личный вопрос',
                    'Предсказание на ближайшее будущее',
                ],
            ],
            [
                'name'         => 'Обмен контактами',
                'accent_color' => '#18ca04',
                'image_path'   => 'cat_images/cat_star_obmen_contactami.png',
                'description'  => 'Познакомьтесь и добавьте друг друга в соцсетях или мессенджерах.',
                'name_suggestions' => [
                    'Добавить друг друга в соцсетях',
                    'Обменяться Instagram',
                    'Обменяться контактами',
                ],
            ],
            [
                'name'         => 'Языковая практика',
                'accent_color' => '#ffffff',
                'image_path'   => 'cat_images/cat_star_yazikovaya_practica.png',
                'description'  => 'Улучшайте иностранный язык через живое общение.',
                'name_suggestions' => [
                    'Практика разговорного английского',
                    'Разговорная практика японского',
                    'Живой разговор на иностранном языке',
                ],
            ],
            [
                'name'         => 'День со мной',
                'accent_color' => '#da1b90',
                'image_path'   => 'cat_images/cat_star_den_so_mnoy.png',
                'description'  => 'Приятно проведите время вместе так, как пожелаете.',
                'name_suggestions' => [
                    'Поиграть вместе онлайн',
                    'Провести вечер в разговоре',
                    'Просто пообщаться',
                ],
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
