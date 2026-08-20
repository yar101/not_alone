<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Seeder;

class HelpCenterSeeder extends Seeder
{
    public function run(): void
    {
        HelpArticle::query()->delete();
        HelpCategory::query()->delete();

        $data = [
            [
                'title' => ['ru' => 'Что такое not alone?', 'en' => 'What is not alone?'],
                'articles' => [
                    [
                        'title' => ['ru' => 'О сервисе', 'en' => 'About service'],
                        'content' => [
                            'ru' => '<p>not alone — это сервис для поиска собеседников и живого общения. Здесь вы можете найти людей со схожими интересами и просто поговорить.</p><p>Мы верим, что каждый человек заслуживает внимания и живого общения.</p>',
                            'en' => '<p>not alone is a service for finding interlocutors and live communication. Here you can find people with similar interests and just talk.</p><p>We believe that everyone deserves attention and live communication.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Для кого это?', 'en' => 'Who is it for?'],
                        'content' => [
                            'ru' => '<p>Сервис подходит для всех, кто хочет найти нового собеседника, поделиться мыслями или просто не быть одному. Неважно, сколько вам лет и где вы находитесь.</p>',
                            'en' => '<p>The service is suitable for anyone who wants to find a new interlocutor, share thoughts, or just not be alone. It does not matter how old you are or where you are located.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Это бесплатно?', 'en' => 'Is it free?'],
                        'content' => [
                            'ru' => '<p>Базовые функции сервиса полностью бесплатны. Вы можете общаться без ограничений и без скрытых платежей.</p><p>Расширенные возможности могут быть доступны по подписке.</p>',
                            'en' => '<p>Basic features of the service are completely free. You can communicate without limits and hidden fees.</p><p>Advanced features may be available with a subscription.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Нужно ли устанавливать приложение?', 'en' => 'Do I need to install an app?'],
                        'content' => [
                            'ru' => '<p>Нет, not alone работает прямо в браузере. Для удобства вы также можете установить мобильное приложение — оно доступно в App Store и Google Play.</p>',
                            'en' => '<p>No, not alone works directly in the browser. For convenience, you can also install the mobile app — it is available in the App Store and Google Play.</p>',
                        ],
                    ],
                ],
            ],
            [
                'title' => ['ru' => 'Регистрация', 'en' => 'Registration'],
                'articles' => [
                    [
                        'title' => ['ru' => 'Как создать аккаунт?', 'en' => 'How to create an account?'],
                        'content' => [
                            'ru' => '<p>Нажмите кнопку «Начать» на главной странице. Введите email и придумайте пароль. После подтверждения email ваш аккаунт будет активирован — это займёт меньше минуты.</p>',
                            'en' => '<p>Click the "Start" button on the main page. Enter your email and create a password. After email confirmation, your account will be activated — it takes less than a minute.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Забыл пароль — что делать?', 'en' => 'Forgot password - what to do?'],
                        'content' => [
                            'ru' => '<p>На странице входа нажмите «Забыл пароль». Введите email, привязанный к аккаунту, и мы отправим вам ссылку для сброса пароля.</p>',
                            'en' => '<p>On the login page, click "Forgot Password". Enter the email linked to the account, and we will send you a link to reset your password.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Как удалить аккаунт?', 'en' => 'How to delete the account?'],
                        'content' => [
                            'ru' => '<p>Вы можете удалить аккаунт в разделе «Настройки → Аккаунт → Удалить аккаунт». После удаления все данные будут безвозвратно стёрты в течение 30 дней.</p>',
                            'en' => '<p>You can delete your account in the section "Settings → Account → Delete Account". After deletion, all data will be permanently erased within 30 days.</p>',
                        ],
                    ],
                ],
            ],
            [
                'title' => ['ru' => 'Общение', 'en' => 'Communication'],
                'articles' => [
                    [
                        'title' => ['ru' => 'Как найти собеседника?', 'en' => 'How to find an interlocutor?'],
                        'content' => [
                            'ru' => '<p>После входа перейдите в раздел «Поиск». Можно фильтровать по интересам, возрасту и другим параметрам. Отправьте запрос на общение — и начните разговор!</p>',
                            'en' => '<p>After logging in, go to the "Search" section. You can filter by interests, age, and other parameters. Send a chat request — and start talking!</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Можно ли отказать собеседнику?', 'en' => 'Can I reject an interlocutor?'],
                        'content' => [
                            'ru' => '<p>Да, общение добровольно. Вы можете завершить беседу в любой момент или отклонить входящий запрос без объяснения причин.</p>',
                            'en' => '<p>Yes, communication is voluntary. You can end the conversation at any time or reject an incoming request without explaining reasons.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Что если собеседник нарушает правила?', 'en' => 'What if the interlocutor violates the rules?'],
                        'content' => [
                            'ru' => '<p>Нажмите на профиль пользователя и выберите «Пожаловаться». Мы рассмотрим жалобу в течение 24 часов. Вы также можете сразу заблокировать этого человека.</p>',
                            'en' => '<p>Click on the user profile and choose "Report". We will review the complaint within 24 hours. You can also block this person immediately.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Сколько человек можно добавить в контакты?', 'en' => 'How many people can I add to contacts?'],
                        'content' => [
                            'ru' => '<p>На бесплатном тарифе — до 50 контактов. С подпиской лимит снимается, и вы можете добавлять неограниченное количество людей.</p>',
                            'en' => '<p>On the free plan — up to 50 contacts. With a subscription, the limit is removed, and you can add an unlimited number of people.</p>',
                        ],
                    ],
                ],
            ],
            [
                'title' => ['ru' => 'Настройки', 'en' => 'Settings'],
                'articles' => [
                    [
                        'title' => ['ru' => 'Как изменить профиль?', 'en' => 'How to edit profile?'],
                        'content' => [
                            'ru' => '<p>Перейдите в «Настройки → Профиль». Здесь можно изменить аватар, имя, описание и список интересов. Изменения сохраняются автоматически.</p>',
                            'en' => '<p>Go to "Settings → Profile". Here you can change your avatar, name, description, and list of interests. Changes are saved automatically.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Как настроить уведомления?', 'en' => 'How to configure notifications?'],
                        'content' => [
                            'ru' => '<p>В разделе «Настройки → Уведомления» выберите, о чём хотите получать оповещения: новые сообщения, запросы на общение, системные новости.</p>',
                            'en' => '<p>In the "Settings → Notifications" section, select what you want to receive alerts about: new messages, chat requests, system news.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Как управлять приватностью?', 'en' => 'How to manage privacy?'],
                        'content' => [
                            'ru' => '<p>В «Настройки → Приватность» вы управляете видимостью профиля: кто может вас найти, видеть онлайн-статус и отправлять запросы на общение.</p>',
                            'en' => '<p>In "Settings → Privacy" you manage profile visibility: who can find you, see online status, and send chat requests.</p>',
                        ],
                    ],
                ],
            ],
            [
                'title' => ['ru' => 'Безопасность', 'en' => 'Security'],
                'articles' => [
                    [
                        'title' => ['ru' => 'Как защищены мои данные?', 'en' => 'How is my data protected?'],
                        'content' => [
                            'ru' => '<p>Все данные передаются по зашифрованному соединению (HTTPS/TLS). Мы не продаём личные данные третьим лицам и соблюдаем требования GDPR.</p>',
                            'en' => '<p>All data is transmitted via a secure connection (HTTPS/TLS). We do not sell personal data to third parties and comply with GDPR requirements.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Как заблокировать пользователя?', 'en' => 'How to block a user?'],
                        'content' => [
                            'ru' => '<p>Откройте профиль пользователя и нажмите «Заблокировать». После этого он не сможет видеть ваш профиль, писать вам или добавлять вас в контакты.</p>',
                            'en' => '<p>Open the user profile and click "Block". After this, they will not be able to see your profile, write to you, or add you to contacts.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Как подать жалобу?', 'en' => 'How to file a complaint?'],
                        'content' => [
                            'ru' => '<p>На странице профиля пользователя или в активном чате нажмите «⋯ → Пожаловаться». Выберите причину и при желании добавьте комментарий. Мы рассмотрим в течение 24 часов.</p>',
                            'en' => '<p>On the user profile page or in an active chat, click "⋯ → Report". Select a reason and optionally add a comment. We will review it within 24 hours.</p>',
                        ],
                    ],
                ],
            ],
            [
                'title' => ['ru' => 'Контакты', 'en' => 'Contacts'],
                'articles' => [
                    [
                        'title' => ['ru' => 'Как связаться с поддержкой?', 'en' => 'How to contact support?'],
                        'content' => [
                            'ru' => '<p>Напишите нам на <strong>support@noalone.app</strong> — мы отвечаем в течение 24 часов в рабочие дни. Также можно воспользоваться формой обратной связи внутри приложения.</p>',
                            'en' => '<p>Write to us at <strong>support@noalone.app</strong> — we respond within 24 hours on business days. You can also use the feedback form inside the application.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Где вы в соцсетях?', 'en' => 'Where are you on social media?'],
                        'content' => [
                            'ru' => '<p>Мы есть в Telegram, ВКонтакте и Instagram. Ссылки на актуальные страницы можно найти в подвале сайта.</p>',
                            'en' => '<p>We are on Telegram, VKontakte, and Instagram. Links to active pages can be found in the footer of the site.</p>',
                        ],
                    ],
                    [
                        'title' => ['ru' => 'Как стать партнёром?', 'en' => 'How to become a partner?'],
                        'content' => [
                            'ru' => '<p>По вопросам сотрудничества и партнёрства пишите на <strong>partners@noalone.app</strong>. Расскажите о вашем проекте, и мы рассмотрим предложение.</p>',
                            'en' => '<p>For cooperation and partnership questions, write to <strong>partners@noalone.app</strong>. Tell us about your project, and we will consider the proposal.</p>',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($data as $catIdx => $catData) {
            $category = new HelpCategory;
            $category->sort_order = $catIdx;
            $category->setTranslation('title', 'ru', $catData['title']['ru']);
            $category->setTranslation('title', 'en', $catData['title']['en']);
            $category->save();

            foreach ($catData['articles'] as $artIdx => $artData) {
                $article = new HelpArticle;
                $article->category_id = $category->id;
                $article->sort_order = $artIdx;
                $article->setTranslation('title', 'ru', $artData['title']['ru']);
                $article->setTranslation('title', 'en', $artData['title']['en']);
                $article->setTranslation('content', 'ru', $artData['content']['ru']);
                $article->setTranslation('content', 'en', $artData['content']['en']);
                $article->save();
            }
        }
    }
}
