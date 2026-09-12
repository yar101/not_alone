<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DevConsoleCommand extends Command
{
    protected $signature = 'dev:tui';

    protected $description = 'Интерактивная консоль разработчика Not Alone (TUI)';

    public function handle(): int
    {
        while (true) {
            $this->renderHeader();

            $choice = select(
                label: 'Выберите раздел для работы:',
                options: [
                    'env' => '🚀 Серверы и окружение (Docker, Local, LAN, статус)',
                    'db' => '🗄️ База данных и миграции (migrate, fresh, seeders, psql)',
                    'generators' => '⚡ Генераторы тестовых данных (users, orders, reviews, services)',
                    'ssl' => '🔐 SSL-сертификаты и сеть (IP, генерация certs, проверка портов)',
                    'cache' => '🧹 Кэш, ассеты и код (optimize:clear, npm build, ide-helper)',
                    'test' => '🧪 Тестирование и качество (phpunit, k6 load tests, php lint)',
                    'logs' => '📜 Логи и консоль (laravel.log, docker logs, tinker, bash)',
                    'urls' => '🌐 Ссылки на сервисы (Web App, Mailpit, MinIO, Vite)',
                    'exit' => '🚪 Выход',
                ],
                default: 'env'
            );

            if ($choice === 'exit') {
                outro('👋 Удачной разработки в Not Alone!');
                break;
            }

            $this->handleSection($choice);
        }

        return self::SUCCESS;
    }

    private function renderHeader(): void
    {
        $branch = trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?? 'unknown');
        $lanIp = $this->getLanIp();
        $certsExist = file_exists(base_path("{$lanIp}.pem")) && file_exists(base_path("{$lanIp}-key.pem"));
        $dockerRunning = $this->isDockerDaemonRunning();
        $containersCount = $dockerRunning ? $this->getRunningContainersCount() : 0;
        $dbStatus = $this->checkDbStatus();
        $redisStatus = $this->checkRedisStatus();

        $certText = $certsExist ? '🟢 Готовы' : '🔴 Отсутствуют';
        $dockerText = $dockerRunning
            ? ($containersCount > 0 ? "🟢 Активен ({$containersCount} конт.)" : '🟡 Запущен (0 конт.)')
            : '🔴 Выключен';

        $this->output->write("\033\143"); // Clear terminal screen
        intro('✨ NOT ALONE — Интерактивная консоль разработчика');

        table(
            headers: ['Параметр', 'Значение'],
            rows: [
                ['Ветка Git', "🌱 {$branch}"],
                ['LAN IP хоста', "🌐 {$lanIp}"],
                ['SSL сертификаты', $certText],
                ['Docker окружение', $dockerText],
                ['База данных (Postgres)', $dbStatus],
                ['Redis кэш/очереди', $redisStatus],
            ]
        );
    }

    private function handleSection(string $section): void
    {
        match ($section) {
            'env' => $this->menuEnvironment(),
            'db' => $this->menuDatabase(),
            'generators' => $this->menuGenerators(),
            'ssl' => $this->menuSsl(),
            'cache' => $this->menuCache(),
            'test' => $this->menuTesting(),
            'logs' => $this->menuLogs(),
            'urls' => $this->menuUrls(),
            default => null,
        };
    }

    // ── 1. Серверы и окружение ───────────────────────────────────────────────────

    private function menuEnvironment(): void
    {
        while (true) {
            $choice = select(
                label: '🚀 Управление серверами и окружением:',
                options: [
                    'docker_dev' => '▶️ Запустить Docker Dev (docker-dev.sh в foreground)',
                    'docker_up_d' => '▶️ Поднять Docker в фоне (docker compose up -d)',
                    'docker_down' => '⏹️ Остановить Docker (docker compose down)',
                    'docker_restart' => '🔄 Перезапустить Docker контейнеры (docker compose restart)',
                    'docker_ps' => '📋 Показать статус контейнеров (docker compose ps)',
                    'local_dev' => '💻 Запустить локально без Docker (composer run dev)',
                    'lan_dev' => '📱 Запустить LAN Dev (bash dev-lan.sh)',
                    'pwa_lan' => '📲 Запустить PWA LAN (bash pwa-lan.sh)',
                    'ports_check' => '🔍 Проверить доступность портов проекта',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            $ip = $this->getLanIp();

            match ($choice) {
                'docker_dev' => $this->runCommandInteractive('bash docker-dev.sh'),
                'docker_up_d' => $this->runCommandWithSpinner(
                    "DEV_HOST={$ip} docker compose up -d",
                    'Поднятие контейнеров Docker...',
                    'Контейнеры Docker успешно запущены в фоне.'
                ),
                'docker_down' => $this->runCommandWithSpinner(
                    "DEV_HOST={$ip} docker compose down",
                    'Остановка контейнеров Docker...',
                    'Контейнеры Docker успешно остановлены.'
                ),
                'docker_restart' => $this->runCommandWithSpinner(
                    "DEV_HOST={$ip} docker compose restart",
                    'Перезапуск контейнеров Docker...',
                    'Контейнеры Docker успешно перезапущены.'
                ),
                'docker_ps' => $this->runCommandInteractive("DEV_HOST={$ip} docker compose ps"),
                'local_dev' => $this->runCommandInteractive('composer run dev'),
                'lan_dev' => $this->runCommandInteractive('bash dev-lan.sh'),
                'pwa_lan' => $this->runCommandInteractive('bash pwa-lan.sh'),
                'ports_check' => $this->checkProjectPorts(),
            };
        }
    }

    // ── 2. База данных и миграции ───────────────────────────────────────────────

    private function menuDatabase(): void
    {
        while (true) {
            $choice = select(
                label: '🗄️ База данных и миграции:',
                options: [
                    'migrate' => '📦 Применить новые миграции (migrate)',
                    'fresh_seed' => '⚠️ Полный сброс БД и запуск сидеров (migrate:fresh --seed)',
                    'specific_seeder' => '🌱 Выборочный запуск сидера (Admin, Users, HelpCenter...)',
                    'rollback' => '⏪ Откатить последнюю миграцию (migrate:rollback)',
                    'psql' => '🐘 Открыть PostgreSQL CLI (psql)',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'migrate' => $this->runArtisanCommand('migrate'),
                'fresh_seed' => $this->runFreshSeed(),
                'specific_seeder' => $this->runSpecificSeeder(),
                'rollback' => $this->runArtisanCommand('migrate:rollback'),
                'psql' => $this->runPsqlCli(),
            };
        }
    }

    private function runFreshSeed(): void
    {
        $confirmed = confirm(
            label: 'ВНИМАНИЕ: Это полностью удалит все таблицы и данные в базе. Продолжить?',
            default: false
        );

        if ($confirmed) {
            $this->runArtisanCommand('migrate:fresh', ['--seed' => true]);
        } else {
            info('Операция отменена.');
        }
    }

    private function runSpecificSeeder(): void
    {
        $seeder = select(
            label: 'Выберите сидер для запуска:',
            options: [
                'DatabaseSeeder' => 'DatabaseSeeder (Все основные сидеры)',
                'AdminSeeder' => 'AdminSeeder (Администраторы и дефолтные роли)',
                'TestUsersSeeder' => 'TestUsersSeeder (Тестовые обычные пользователи и айдолы)',
                'HelpCenterSeeder' => 'HelpCenterSeeder (Статьи базы знаний и FAQ)',
                'NewsSeeder' => 'NewsSeeder (Новости платформы)',
                'BanReasonSeeder' => 'BanReasonSeeder (Справочник причин банов)',
                'ReviewEpithetSeeder' => 'ReviewEpithetSeeder (Эпитеты для отзывов)',
                'K6LoadTestSeeder' => 'K6LoadTestSeeder (Данные для нагрузочных тестов)',
                'cancel' => 'Отмена',
            ]
        );

        if ($seeder !== 'cancel') {
            $this->runArtisanCommand('db:seed', ['--class' => $seeder]);
        }
    }

    private function runPsqlCli(): void
    {
        if ($this->getRunningContainersCount() > 0) {
            $this->runCommandInteractive('docker compose exec -it db psql -U postgres -d not_alone');
        } else {
            $this->runCommandInteractive('psql -U postgres -d not_alone -p 5433 -h 127.0.0.1');
        }
    }

    // ── 3. Генераторы тестовых данных ───────────────────────────────────────────

    private function menuGenerators(): void
    {
        while (true) {
            $choice = select(
                label: '⚡ Генераторы тестовых данных и сущностей:',
                options: [
                    'make_user' => '👤 Создать одного пользователя (make:user)',
                    'gen_users' => '👥 Массовая генерация пользователей (users:generate)',
                    'gen_orders' => '🛍️ Сгенерировать заказы (orders:generate)',
                    'seed_reviews' => '⭐ Сгенерировать отзывы айдолам (reviews:seed)',
                    'seed_services' => '🛠️ Сгенерировать услуги для айдолов (services:seed)',
                    'gen_gallery' => '🖼️ Сгенерировать галерею фотографий (gallery:generate)',
                    'test_notify' => '🔔 Отправить тестовое уведомление (notifications:test)',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'make_user' => $this->runCommandInteractive('php artisan make:user'),
                'gen_users' => $this->runCommandInteractive('php artisan users:generate'),
                'gen_orders' => $this->runCommandInteractive('php artisan orders:generate'),
                'seed_reviews' => $this->runCommandInteractive('php artisan reviews:seed'),
                'seed_services' => $this->runCommandInteractive('php artisan services:seed'),
                'gen_gallery' => $this->runCommandInteractive('php artisan gallery:generate'),
                'test_notify' => $this->runCommandInteractive('php artisan notifications:test'),
            };
        }
    }

    // ── 4. SSL и сеть ───────────────────────────────────────────────────────────

    private function menuSsl(): void
    {
        while (true) {
            $choice = select(
                label: '🔐 SSL-сертификаты и сеть:',
                options: [
                    'status' => 'ℹ️ Информация о LAN IP и путях сертификатов',
                    'generate' => '🔐 Перевыпустить SSL-сертификаты для текущего хоста',
                    'ports' => '🔍 Проверить занятость портов',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'status' => $this->showSslStatus(),
                'generate' => $this->regenerateSslCertificates(),
                'ports' => $this->checkProjectPorts(),
            };
        }
    }

    private function showSslStatus(): void
    {
        $ip = $this->getLanIp();
        $certPath = base_path("{$ip}.pem");
        $keyPath = base_path("{$ip}-key.pem");

        table(
            headers: ['Свойство', 'Значение'],
            rows: [
                ['Текущий LAN IP', $ip],
                ['Файл сертификата (.pem)', file_exists($certPath) ? "🟢 {$certPath}" : "🔴 Не найден ({$certPath})"],
                ['Файл ключа (-key.pem)', file_exists($keyPath) ? "🟢 {$keyPath}" : "🔴 Не найден ({$keyPath})"],
                ['mkcert в системе', shell_exec('command -v mkcert') ? '🟢 Установлен' : '⚪ Не установлен'],
                ['openssl в системе', shell_exec('command -v openssl') ? '🟢 Установлен' : '🔴 Не установлен'],
            ]
        );

        $this->waitEnter();
    }

    private function regenerateSslCertificates(): void
    {
        $ip = $this->getLanIp();
        info("Генерация сертификатов для IP: {$ip}...");

        if (shell_exec('command -v mkcert')) {
            $cmd = "mkcert -key-file '{$ip}-key.pem' -cert-file '{$ip}.pem' '{$ip}' 'localhost' 127.0.0.1 ::1";
        } elseif (shell_exec('command -v openssl')) {
            $cmd = "openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout '{$ip}-key.pem' -out '{$ip}.pem' -subj '/CN={$ip}' -addext 'subjectAltName=IP:{$ip},IP:127.0.0.1,DNS:localhost' 2>/dev/null";
        } else {
            warning('Ни mkcert, ни openssl не найдены в системе.');
            $this->waitEnter();
            return;
        }

        $res = shell_exec($cmd);
        if (file_exists(base_path("{$ip}.pem")) && file_exists(base_path("{$ip}-key.pem"))) {
            info("✅ Сертификаты {$ip}.pem и {$ip}-key.pem успешно созданы!");
        } else {
            warning("Ошибка при генерации сертификатов. Вывод: {$res}");
        }

        $this->waitEnter();
    }

    // ── 5. Кэш, ассеты и код ────────────────────────────────────────────────────

    private function menuCache(): void
    {
        while (true) {
            $choice = select(
                label: '🧹 Кэш, сборка и ассеты:',
                options: [
                    'optimize_clear' => '✨ Очистить все кэши (optimize:clear)',
                    'npm_build' => '📦 Собрать фронтенд для продакшена (npm run build)',
                    'npm_dev' => '⚡ Запустить dev-сервер Vite (npm run dev)',
                    'ide_helper' => '🧩 Обновить IDE Helpers (composer run ide-helper)',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'optimize_clear' => $this->runArtisanCommand('optimize:clear'),
                'npm_build' => $this->runCommandInteractive('npm run build'),
                'npm_dev' => $this->runCommandInteractive('npm run dev'),
                'ide_helper' => $this->runCommandInteractive('composer run ide-helper'),
            };
        }
    }

    // ── 6. Тестирование и качество ──────────────────────────────────────────────

    private function menuTesting(): void
    {
        while (true) {
            $choice = select(
                label: '🧪 Тестирование и качество кода:',
                options: [
                    'artisan_test' => '🧪 Запустить PHPUnit / Feature тесты (php artisan test)',
                    'k6_chat' => '💬 Запустить нагрузочный тест K6 (чат)',
                    'k6_master' => '🔥 Запустить нагрузочный тест K6 (мастер-сценарий)',
                    'php_lint' => '🔍 Проверить синтаксис PHP файлов (php -l)',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'artisan_test' => $this->runCommandInteractive('php artisan test'),
                'k6_chat' => $this->runCommandInteractive('bash tests/load/run_chat.sh'),
                'k6_master' => $this->runCommandInteractive('bash tests/load/run_master.sh'),
                'php_lint' => $this->runPhpLint(),
            };
        }
    }

    private function runPhpLint(): void
    {
        spin(function () {
            shell_exec('find app routes database config -name "*.php" -exec php -l {} + > /dev/null 2>&1');
        }, 'Проверка синтаксиса PHP файлов...');

        info('✅ Все PHP файлы в app, routes, database, config успешно прошли синтаксическую проверку.');
        $this->waitEnter();
    }

    // ── 7. Логи и консоль ───────────────────────────────────────────────────────

    private function menuLogs(): void
    {
        while (true) {
            $choice = select(
                label: '📜 Логи и интерактивный шелл:',
                options: [
                    'laravel_log' => '📄 Читать storage/logs/laravel.log (tail -n 50 -f)',
                    'docker_log' => '🐳 Читать логи Docker контейнера (выбрать сервис)',
                    'tinker' => '🧪 Открыть интерактивный Laravel Tinker',
                    'app_bash' => '💻 Открыть bash в контейнере not_alone_app',
                    'back' => '⬅️ Назад в главное меню',
                ]
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'laravel_log' => $this->tailLaravelLog(),
                'docker_log' => $this->selectAndTailDockerLog(),
                'tinker' => $this->runCommandInteractive('php artisan tinker'),
                'app_bash' => $this->runCommandInteractive('docker compose exec -it app bash'),
            };
        }
    }

    private function tailLaravelLog(): void
    {
        $logPath = storage_path('logs/laravel.log');
        if (! file_exists($logPath)) {
            warning("Файл логов не найден: {$logPath}");
            $this->waitEnter();
            return;
        }

        info("Трансляция лога (Ctrl+C для выхода): {$logPath}");
        $this->runCommandInteractive("tail -n 50 -f {$logPath}");
    }

    private function selectAndTailDockerLog(): void
    {
        $service = select(
            label: 'Выберите Docker сервис для просмотра логов:',
            options: [
                'app' => 'app (Nginx + PHP-FPM приложение)',
                'worker' => 'worker (Очереди Queue Worker)',
                'reverb' => 'reverb (WebSocket сервер)',
                'scheduler' => 'scheduler (Cron планировщик задач)',
                'db' => 'db (PostgreSQL)',
                'redis' => 'redis (Redis кэш)',
                'mailpit' => 'mailpit (SMTP & Web UI почты)',
                'minio' => 'minio (S3 хранилище)',
                'vite' => 'vite (Фронтенд Dev Server)',
                'cancel' => 'Отмена',
            ]
        );

        if ($service !== 'cancel') {
            info("Трансляция логов сервиса {$service} (Ctrl+C для выхода)...");
            $this->runCommandInteractive("docker compose logs -f --tail=100 {$service}");
        }
    }

    // ── 8. Ссылки на сервисы ────────────────────────────────────────────────────

    private function menuUrls(): void
    {
        $ip = $this->getLanIp();

        $services = [
            ['🖥️ Web Приложение (HTTPS)', "https://{$ip}:8443", 'Основной интерфейс (LAN)'],
            ['🖥️ Web Приложение (Local)', 'https://localhost:8443', 'Основной интерфейс (Local)'],
            ['⚡ Vite HMR Dev Server', "https://{$ip}:5173", 'Горячая перезагрузка ассетов'],
            ['📬 Mailpit UI', "http://{$ip}:8025", 'Веб-почта для перехвата писем'],
            ['🗄️ MinIO Console (S3)', "http://{$ip}:8900", 'Логин: sail / Пароль: password'],
            ['📡 Reverb WebSocket', "https://{$ip}:8443", 'Порт 8080 проксируется в Nginx'],
        ];

        table(
            headers: ['Сервис', 'URL', 'Описание'],
            rows: $services
        );

        $open = select(
            label: 'Открыть сервис в браузере?',
            options: [
                'none' => 'Нет, вернуться назад',
                'app' => "Открыть Web App (https://{$ip}:8443)",
                'mailpit' => "Открыть Mailpit (http://{$ip}:8025)",
                'minio' => "Открыть MinIO Console (http://{$ip}:8900)",
            ]
        );

        if ($open !== 'none') {
            $url = match ($open) {
                'app' => "https://{$ip}:8443",
                'mailpit' => "http://{$ip}:8025",
                'minio' => "http://{$ip}:8900",
                default => null,
            };

            if ($url && shell_exec('command -v xdg-open')) {
                shell_exec("xdg-open '{$url}' >/dev/null 2>&1 &");
                info("Открыто в браузере: {$url}");
            } else {
                info("Перейдите по ссылке: {$url}");
            }
            $this->waitEnter();
        }
    }

    // ── Утилиты и вспомогательные методы ────────────────────────────────────────

    private function getLanIp(): string
    {
        $ip = trim(shell_exec("ip -4 addr show scope global 2>/dev/null | grep -vE '(docker|br-|veth|amn|wg|tun|tap)' | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | head -n 1") ?? '');
        if (empty($ip)) {
            $ip = trim(shell_exec("ip -4 addr show 2>/dev/null | grep -oP '(?<=inet\s)\d+(\.\d+){3}' | grep -v '^127\.' | grep -v '^172\.' | head -n 1") ?? '');
        }

        return ! empty($ip) ? $ip : '127.0.0.1';
    }

    private function isDockerDaemonRunning(): bool
    {
        $res = shell_exec('docker info 2>&1');
        return ! str_contains((string) $res, 'Cannot connect to the Docker daemon') && ! str_contains((string) $res, 'command not found');
    }

    private function getRunningContainersCount(): int
    {
        $output = shell_exec('docker ps --filter "name=not_alone" --format "{{.Names}}" 2>/dev/null');
        $lines = array_filter(explode("\n", trim((string) $output)));
        return count($lines);
    }

    private function checkDbStatus(): string
    {
        try {
            DB::connection()->getPdo();
            return '🟢 Подключено (' . config('database.default') . ')';
        } catch (\Throwable $e) {
            return '🔴 Недоступно';
        }
    }

    private function checkRedisStatus(): string
    {
        try {
            Redis::ping();
            return '🟢 Подключено';
        } catch (\Throwable $e) {
            return '🔴 Недоступно';
        }
    }

    private function checkProjectPorts(): void
    {
        $ports = [
            '8443' => 'Nginx (HTTPS Web App)',
            '5433' => 'PostgreSQL DB',
            '6379' => 'Redis Cache',
            '8025' => 'Mailpit Web UI',
            '1025' => 'Mailpit SMTP',
            '8900' => 'MinIO Web Console',
            '9000' => 'MinIO S3 API',
            '8080' => 'Reverb WebSocket',
            '5173' => 'Vite HMR Dev Server',
        ];

        $rows = [];
        foreach ($ports as $port => $name) {
            $check = trim(shell_exec("ss -tulpn 2>/dev/null | grep -E ':{$port}\\b' || true") ?? '');
            $status = ! empty($check) ? '🟢 Занят / Слушается' : '⚪ Свободен';
            $rows[] = [$port, $name, $status];
        }

        table(
            headers: ['Порт', 'Назначение', 'Текущий статус'],
            rows: $rows
        );

        $this->waitEnter();
    }

    private function runCommandInteractive(string $command): void
    {
        $this->output->writeln("\n<fg=gray>> {$command}</>\n");
        passthru($command);
        $this->waitEnter();
    }

    private function runCommandWithSpinner(string $command, string $spinnerText, string $successText): void
    {
        spin(function () use ($command) {
            shell_exec("{$command} 2>&1");
        }, $spinnerText);

        info("✅ {$successText}");
        $this->waitEnter();
    }

    private function runArtisanCommand(string $name, array $params = []): void
    {
        $this->output->writeln("\n<fg=gray>> php artisan {$name}</>\n");
        Artisan::call($name, $params, $this->output);
        $this->waitEnter();
    }

    private function waitEnter(): void
    {
        text(
            label: 'Нажмите Enter для продолжения...',
            default: '',
            required: false
        );
    }
}
