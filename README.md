# NoAlone

Платформа для айдолов и их фанатов. Laravel 12 + Inertia.js v2 + Vue 3.

## Стек

- **Backend:** Laravel 12, PostgreSQL
- **Frontend:** Inertia.js v2, Vue 3 (Composition API), Vite
- **Auth:** два гарда — `web` (пользователи) и `admin` (администраторы)

## Первоначальная настройка

```bash
cp .env.example .env
composer install
npm install

php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminSeeder
```

## Запуск

Для работы реалтайм-уведомлений нужно запускать **три процесса одновременно** (в разных терминалах):

```bash
# 1. Laravel dev-сервер
php artisan serve

# 2. Vite (фронтенд + HMR)
npm run dev

# 3. Reverb WebSocket-сервер (реалтайм-уведомления)
php artisan reverb:start
```

> Без `reverb:start` колокольчик уведомлений работать не будет (страница не получит push от сервера).

> **Примечание:** WebSocket-события (`NewNotification`) используют `ShouldBroadcastNow` и отправляются в Reverb **напрямую**, минуя очередь. Если в будущем переключить на `ShouldBroadcast` — нужно будет также запускать `php artisan queue:work`.

## Тестовые данные

### Администратор

Сидер создаёт одного администратора:

```bash
php artisan db:seed --class=AdminSeeder
```

| Поле  | Значение              |
|-------|-----------------------|
| Email | admin@noalone.test    |
| Пароль | password             |

Панель доступна по адресу `/admin`.

### Генерация пользователей

```bash
php artisan users:generate {count}
```

**Пример — создать 100 пользователей:**

```bash
php artisan users:generate 100
```

**Что создаётся:**

- Русскоязычные имена (Faker `ru_RU`)
- Пароль `123123` для всех
- Случайный пол (`male` / `female` / без пола)
- Случайная дата рождения, возраст 18–50 лет
- ~40% пользователей становятся айдолами (`is_idol = true` + запись в `idol_applications` со статусом `approved`)

## Структура

```
app/
  Http/Controllers/
    Admin/          — контроллеры админ-панели
    Idol/           — контроллеры idol flow
  Models/
  Console/Commands/
    GenerateUsers.php
resources/js/
  Pages/
    Admin/          — страницы админ-панели
    Idol/           — страницы idol flow
    Profile/        — профиль пользователя
  Layouts/
    AppLayout.vue   — шапка для авторизованных страниц
    AdminLayout.vue — шапка для админ-панели
routes/
  web.php
  admin.php
```
