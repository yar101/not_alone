<!-- markdownlint-disable MD013 -->
# NoAlone

Платформа для айдолов и их фанатов. Laravel + Inertia.js v2 + Vue 3.

---

## 🛠 Стек технологий

- **Backend:** Laravel 11/12, PHP 8.4+, PostgreSQL
- **Frontend:** Inertia.js v2, Vue 3 (Composition API), Vite, Tailwind CSS, Element Plus
- **Real-time & Фоновые задачи:** Laravel Reverb (WebSocket), Database Queues, Task Scheduler
- **Хранилище медиа:** Локальный приватный диск / MinIO (S3) с защитой через `X-Accel-Redirect`
- **Аутентификация:** два гарда — `web` (пользователи/айдолы) и `admin` (администраторы)

---

## 🚀 Способы запуска проекта

Разработку можно вести двумя способами:

1. [В Docker Compose](#1-запуск-в-docker-compose-рекомендуется) _(всё окружение изолировано в контейнерах)_
2. [Локально на хост-машине](#2-запуск-локально-на-хосте-без-docker) _(нативно в системе)_

---

### 1. Запуск в Docker Compose (Рекомендуется)

Все зависимости (PHP с необходимыми расширениями, Nginx, PostgreSQL, Redis, Mailpit, MinIO, Reverb, Vite) поднимаются автоматически в изолированных контейнерах.

#### Требования

- Установленный **Docker** с поддержкой **Docker Compose** и **Buildx**.

#### Шаги для запуска

1. **Создайте файл окружения:**

    ```bash
    cp .env.example .env
    ```

2. **Соберите базовый образ приложения:**

    ```bash
    docker compose build
    ```

3. **Запустите стек разработки:**

    ```bash
    composer run docker-dev
    # или напрямую через скрипт:
    bash docker-dev.sh
    ```

Скрипт `docker-dev.sh` автоматически определяет IP машины в локальной сети, настраивает HTTPS-сертификаты и запускает все сервисы.

#### Доступные сервисы

| Сервис                     | Адрес                   | Описание                                                       |
| :------------------------- | :---------------------- | :------------------------------------------------------------- |
| **Веб-приложение (HTTPS)** | `https://<LAN_IP>:8443` | Nginx + PHP-FPM с локальным SSL                                |
| **Vite HMR (HTTPS)**       | `https://<LAN_IP>:5173` | Hot Module Replacement фронтенда                               |
| **Mailpit UI**             | `http://<LAN_IP>:8025`  | Просмотр отправленных тестовых писем                           |
| **PostgreSQL**             | `<LAN_IP>:5433`         | Порт БД, проброшенный на хост                                  |
| **Redis**                  | `<LAN_IP>:6379`         | Кэш и сессии                                                   |
| **MinIO Console**          | `http://<LAN_IP>:8900`  | Веб-интерфейс S3-хранилища (логин: `sail`, пароль: `password`) |

#### Полезные команды внутри контейнера

```bash
docker compose exec app php artisan migrate        # Запуск миграций
docker compose exec app php artisan db:seed        # Запуск сидеров
docker compose exec app php artisan tinker         # Интерактивная консоль Laravel
docker compose exec app php artisan test           # Запуск тестов
```

---

### 2. Запуск локально на хосте (Без Docker)

#### Системные требования

Для запуска проекта нативно в любой операционной системе (Void Linux, Ubuntu/Debian, Fedora/RHEL, Arch Linux, macOS и др.) должны быть установлены следующие компоненты:

1. **PHP 8.2+** (рекомендуется **8.4+**) со следующими расширениями:
    - `bcmath` — математические вычисления
    - `curl` — HTTP-клиент
    - `exif` и `gd` — обработка и оптимизация изображений
    - `gmp` — криптография / WebPush
    - `mbstring` — работа со строками в UTF-8
    - `openssl` — шифрование
    - `pcntl` — управление процессами
    - `pdo_pgsql` и `pgsql` — драйверы PostgreSQL
    - `sockets` — сокеты
    - `xml`, `dom`, `simplexml`, `xmlwriter` — работа с XML
    - `zip` — архивация

2. **СУБД PostgreSQL** (версии 15+).
3. **Node.js** (LTS-версия 20+) и пакетный менеджер **npm**.
4. **Composer** (версии 2+).
5. **Mailpit** _(опционально)_ — перехватчик локальной почты (SMTP на порту `1025`, веб-интерфейс на порту `8025`).
6. **Nginx + PHP-FPM** _(опционально)_ — требуются только для запуска в режиме локальной сети (`dev-lan` / `pwa-lan`).

---

#### Первоначальная настройка

```bash
# 1. Скопировать конфиг окружения
cp .env.example .env

# 2. Установить зависимости PHP и Node.js
composer install
npm install

# 3. Сгенерировать ключ приложения
php artisan key:generate

# 4. Настроить базу данных в .env и применить миграции
php artisan migrate

# 5. Создать администратора
php artisan db:seed --class=AdminSeeder

# 6. Создать симлинк для публичного хранилища
php artisan storage:link
```

#### Настройка WebSocket (Laravel Reverb) в `.env`

Убедитесь, что в файле `.env` указаны параметры Reverb (строки ключей могут быть любыми):

```env
REVERB_APP_ID=no-alone
REVERB_APP_KEY=no-alone-key
REVERB_APP_SECRET=no-alone-secret
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
```

---

#### Варианты запуска

- **Стандартный запуск (localhost):**
  Запускает параллельно встроенный сервер Laravel, Vite, Reverb, обработчик очередей, логгер Pail, планировщик и Mailpit:

    ```bash
    composer run dev
    ```

    _(Приложение доступно по адресу `http://localhost:8000`)_.

- **Запуск для тестирования по локальной сети / со смартфона (HTTPS):**
  Использует `nginx` и `php-fpm` с локальными SSL-сертификатами:

    ```bash
    composer run dev-lan
    ```

    _(Приложение доступно по адресу `https://<LAN_IP>:8443`)_.

- **Запуск PWA в локальной сети:**
  Собирает продакшн-ассеты и запускает стек для проверки Service Worker и offline-режима:

    ```bash
    composer run pwa-lan
    ```

- **Ручной запуск в отдельных терминалах:**

    ```bash
    php artisan serve         # 1. Laravel сервер (порт 8000)
    npm run dev               # 2. Vite HMR (порт 5173)
    php artisan reverb:start  # 3. Reverb WebSocket-сервер (порт 8080)
    php artisan queue:listen  # 4. Воркер очередей
    ```

---

## 👥 Тестовые данные и генераторы

### Администратор

Сидер создаёт учетную запись администратора:

```bash
php artisan db:seed --class=AdminSeeder
```

- **URL панели:** `/admin`
- **Email:** `admin@noalone.test`
- **Пароль:** `password`

### Интерактивное создание пользователя

```bash
php artisan user:make
```

Позволяет интерактивно выбрать тип (`Пользователь`, `Айдол` или `Админ`) и сгенерировать пользователя с заполненным профилем (пароль по умолчанию: `123123`).

### Массовая генерация пользователей

```bash
php artisan users:generate 100
```

Генерирует указанное количество пользователей со случайными данными (имена, даты рождения, ~40% становятся айдолами с одобренными заявками).

### Тестовые услуги айдола

```bash
php artisan services:seed {userId}
```

Создает набор услуг во всех активных категориях для указанного ID айдола.

---

## 🧩 IDE Helper (Автодополнение)

Для генерации мета-файлов и аннотаций PHPDoc для IDE (PhpStorm, VS Code, Zed):

```bash
# Сгенерировать все хелперы сразу:
composer run ide-helper

# Или по отдельности:
php artisan ide-helper:generate   # Фасады Laravel
php artisan ide-helper:models -N  # Модели и связи
php artisan ide-helper:meta       # PhpStorm meta
```

---

## 📂 Структура проекта

```text
app/
  Console/Commands/ — консольные команды генерации пользователей и услуг
  Http/Controllers/
    Admin/          — контроллеры панели администратора
    Idol/           — контроллеры страниц и функций айдолов
  Models/           — Eloquent-модели
resources/
  js/
    Components/     — переиспользуемые Vue-компоненты
    Layouts/        — лейауты приложения (AppLayout, AdminLayout)
    Pages/          — страницы Inertia / Vue (Admin, Idol, Profile и др.)
routes/
  web.php           — основные маршруты приложения
  admin.php         — маршруты панели администратора
docker/             — конфигурации Nginx, PHP-FPM и скрипты для Docker
```

---

## 🌐 Продакшн и деплой

### Архитектура

- **Инструмент деплоя:** Kamal 2 (сборка Docker-образа и доставка на VPS по SSH)
- **Веб-сервер:** Nginx + PHP-FPM в одном контейнере под управлением Supervisord
- **SSL / Прокси:** `kamal-proxy` (автоматические сертификаты Let's Encrypt)
- **БД:** PostgreSQL 17 как Kamal accessory (контейнер на том же VPS)
- **Очередь / сессии / кеш:** database-драйвер
- **WebSocket:** Reverb в отдельном контейнере
- **Docker Registry:** GitHub Container Registry (`ghcr.io/yar101/no-alone`)

### Настройка PHP (php.ini)

Для корректной работы загрузки и сжатия изображений (аватары до 5МБ, посты до 10МБ) на сервере должны быть установлены следующие лимиты:

```ini
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 100
```

### Защита медиафайлов

Фото контент-паков хранятся на **приватном диске** (`storage/app/private`), прямой URL недоступен.
Laravel выдаёт подписанный URL (TTL 30 мин) → `MediaController` проверяет подпись и покупку → отдаёт заголовок `X-Accel-Redirect` → Nginx стримит файл напрямую с диска.

### Роли контейнеров (Dockerfile)

| Роль        | Что запускает                                                  |
| ----------- | -------------------------------------------------------------- |
| `web`       | `migrate` + `config:cache` + supervisord (`nginx` + `php-fpm`) |
| `worker`    | `php artisan queue:work`                                       |
| `scheduler` | `php artisan schedule:work`                                    |
| `reverb`    | `php artisan reverb:start`                                     |

### Команды деплоя (Kamal)

```bash
# Первый деплой (настройка сервера и БД):
DEPLOY_HOST=1.2.3.4 APP_DOMAIN=domain.com kamal setup
kamal env push
DEPLOY_HOST=1.2.3.4 APP_DOMAIN=domain.com kamal deploy

# Последующие обновления:
DEPLOY_HOST=1.2.3.4 APP_DOMAIN=domain.com kamal deploy

# Откат на предыдущий образ:
kamal rollback

# Логи контейнеров:
kamal app logs
kamal app logs -r worker
```

---

## ❓ Решение частых проблем

- **Ошибки прав доступа (`Permission Denied` в `storage/` или `bootstrap/cache`):**

    ```bash
    chmod -R 775 storage bootstrap/cache
    ```

- **Ошибка `composer install` по поводу отсутствующих `ext-bcmath`, `ext-curl`:**
  Убедитесь, что расширения включены в вашем `php.ini` или в `/etc/php*/conf.d/`.
- **Ошибка Docker `pull access denied for no_alone_app` при первом запуске:**
  Сначала выполните `docker compose build`, чтобы образ `no_alone_app:dev` собрался локально.
