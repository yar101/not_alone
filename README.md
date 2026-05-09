# NoAlone

Платформа для айдолов и их фанатов. Laravel 12 + Inertia.js v2 + Vue 3.

## Стек

- **Backend:** Laravel 12, PostgreSQL
- **Frontend:** Inertia.js v2, Vue 3 (Composition API), Vite
- **Auth:** два гарда — `web` (пользователи) и `admin` (администраторы)

## Требования

- PHP 8.4+ с расширениями: `bcmath`, `pgsql`, `gd`
- PostgreSQL
- Node.js + npm
- Composer

> Установка расширений на Fedora/RHEL:
>
> ```bash
> sudo dnf install php-bcmath php-pgsql php-gd
> sudo systemctl restart php-fpm
> ```

## Первоначальная настройка

```bash
cp .env.example .env
composer install
npm install

php artisan key:generate
php artisan migrate
php artisan db:seed --class=AdminSeeder
php artisan storage:link

# Создать симлинк для публичного хранилища (картинки категорий, аватары и т.д.)
php artisan storage:link
```

### Настройка Reverb (WebSocket)

В `.env` значения `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` — произвольные строки, главное чтобы они были заполнены:

```env
REVERB_APP_ID=no-alone
REVERB_APP_KEY=no-alone-key
REVERB_APP_SECRET=no-alone-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

## IDE Helper (автодополнение)

Проект использует [barryvdh/laravel-ide-helper](https://github.com/barryvdh/laravel-ide-helper) для улучшения автодополнения в IDE (PhpStorm, VS Code, Zed и др.).

**Генерация хелперов:**

```bash
# Хелпер для фасадов Laravel (создаёт _ide_helper.php)
php artisan ide-helper:generate

# Хелпер для моделей — добавляет PHPDoc с полями и связями (создаёт _ide_helper_models.php)
php artisan ide-helper:models -N

# Мета-файл для PhpStorm (создаёт .phpstorm.meta.php)
php artisan ide-helper:meta
```

> Файлы `_ide_helper.php`, `_ide_helper_models.php` и `.phpstorm.meta.php` добавлены в `.gitignore` — каждый разработчик генерирует их локально.

**Когда перегенерировать:**

- `ide-helper:models` — после изменения миграций или добавления связей в моделях
- `ide-helper:generate` — после обновления Laravel или добавления новых фасадов

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
| Email | <admin@noalone.test>    |
| Пароль | password             |

Панель доступна по адресу `/admin`.

### Создание одного пользователя (интерактивно)

```bash
php artisan user:make
```

Команда спросит тип и создаст пользователя с рандомными данными:

```
 Кого создать?:
  [0] Обычный пользователь
  [1] Айдол
  [2] Админ
```

**Что создаётся:**

- Русскоязычные имя + фамилия (Faker `ru_RU`)
- Пароль `123123`
- Email сразу подтверждён (`email_verified_at` заполнен)
- Случайный пол и дата рождения (18–40 лет)
- Для айдола: `is_idol = true`, рейтинг 50, запись в `idol_applications` (статус `approved`)
- Для админа: запись в таблице `admins`, вход через `/admin/login`

### Массовая генерация пользователей

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

### `php artisan services:seed {userId}`

Создаёт тестовые услуги во всех активных категориях для указанного пользователя.
Перед созданием показывает имя и email пользователя и запрашивает подтверждение.

```bash
php artisan services:seed 1
```

## Структура

```
app/
  Http/Controllers/
    Admin/          — контроллеры админ-панели
    Idol/           — контроллеры idol flow
  Models/
  Console/Commands/
    MakeUser.php           — интерактивное создание одного пользователя/айдола/админа
    GenerateUsers.php      — массовая генерация тестовых пользователей
    SeedUserServices.php   — наполнение профиля айдола тестовыми услугами
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

## Продакшн

### Стек

- **Деплой:** Kamal 2 (Docker-образ → VPS по SSH)
- **Веб-сервер:** nginx + php-fpm в одном контейнере (supervisord)
- **Прокси / SSL:** kamal-proxy (встроен в Kamal, Let's Encrypt)
- **БД:** PostgreSQL 17 как Kamal accessory (контейнер на том же VPS)
- **Очередь / сессии / кеш:** database-драйвер (Redis не нужен на старте)
- **WebSocket:** Reverb в отдельном контейнере
- **Docker Registry:** GitHub Container Registry (`ghcr.io/yar101/no-alone`)

### Настройка PHP (php.ini)

Для корректной работы загрузки и сжатия изображений (аватары до 5МБ, посты до 10МБ), на сервере должны быть установлены следующие лимиты:

```ini
post_max_size = 15M
upload_max_filesize = 12M
max_file_uploads = 50
```

*После изменения настроек необходимо перезапустить PHP-FPM или Apache.*

### Защита медиафайлов

Фото контент-паков хранятся на **приватном диске** (`storage/app/private`), прямой URL недоступен.

Схема: Laravel выдаёт подписанный URL (TTL 30 мин) → `MediaController` проверяет подпись + покупку → отдаёт заголовок `X-Accel-Redirect` → nginx стримит файл напрямую с диска.

Nginx location: `location /private-media/ { internal; alias /var/www/html/storage/app/private/; }`

### Docker

Один `Dockerfile`, роли разделены через `CONTAINER_ROLE`:

| Роль | Что запускает |
|---|---|
| `web` | migrate + config:cache + supervisord (nginx + php-fpm) |
| `worker` | `php artisan queue:work` |
| `scheduler` | `php artisan schedule:work` |
| `reverb` | `php artisan reverb:start` |

Конфиги: `docker/nginx-prod.conf`, `docker/php-fpm-prod.conf`, `docker/supervisord.conf`, `docker/entrypoint.sh`

### Первый деплой

**Требования:**
- VPS Ubuntu 24.04, SSH-ключ добавлен при создании
- Домен направлен A-записью на IP VPS
- GitHub Personal Access Token с `write:packages` → `KAMAL_REGISTRY_PASSWORD`
- Установлен Kamal: `gem install kamal`

**Заполнить в `.env.production`** (файл локальный, не в git):
```
APP_URL=https://домен
DB_PASSWORD=...          # совпадает с POSTGRES_PASSWORD
POSTGRES_PASSWORD=...
KAMAL_REGISTRY_PASSWORD=ghp_...
MAIL_HOST=...
MAIL_USERNAME=...
MAIL_PASSWORD=...
```

**Команды:**
```bash
DEPLOY_HOST=1.2.3.4 APP_DOMAIN=домен kamal setup   # установить Docker на VPS, поднять postgres
kamal env push                                        # залить секреты на VPS
DEPLOY_HOST=1.2.3.4 APP_DOMAIN=домен kamal deploy   # собрать образ, задеплоить
```

**Последующие деплои:**
```bash
DEPLOY_HOST=1.2.3.4 APP_DOMAIN=домен kamal deploy
```

### Storage volume

Приватные файлы живут на VPS в `/var/www/no-alone/storage` (смонтировано в контейнер). При деплое **не пересоздаются** — данные сохраняются между деплоями.

БД живёт в `/var/www/no-alone/postgres`.

Бэкапы этих двух директорий = полный бэкап данных.

### Откат

```bash
kamal rollback          # откат на предыдущий образ
kamal app logs          # логи web-контейнера
kamal app logs -r worker  # логи worker
```
