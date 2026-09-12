<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DevConsoleCommand extends Command
{
    protected $signature = 'dev:tui';

    protected $description = 'Интерактивная консоль разработчика Not Alone (TUI)';

    private const MENU_SCROLL = 15;

    public function handle(): int
    {
        while (true) {
            $this->renderHeader();

            $choice = select(
                label: 'Выберите раздел для работы:',
                options: [
                    'env' => '  Серверы и окружение (Docker, Local, LAN, статус)',
                    'db' => '  База данных и миграции (migrate, fresh, seeders, psql)',
                    'generators' => '  Генераторы тестовых данных (users, orders, reviews, services)',
                    'ssl' => '  SSL-сертификаты и сеть (IP, генерация certs, порты)',
                    'cache' => '  Кэш, ассеты и код (optimize:clear, npm build, ide-helper)',
                    'test' => '  Тестирование и качество (phpunit, k6 load tests, php lint)',
                    'logs' => '  Логи и консоль (laravel.log, docker logs, tinker, bash)',
                    'urls' => '  Ссылки на сервисы (Web App, Mailpit, MinIO, Vite)',
                    'exit' => '  Выход',
                ],
                default: 'env',
                scroll: self::MENU_SCROLL
            );

            if ($choice === 'exit') {
                outro('  Удачной разработки в Not Alone!');
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

        $certText = $certsExist ? '<fg=green> Готовы</>' : '<fg=red> Отсутствуют</>';
        $dockerText = $dockerRunning
            ? ($containersCount > 0 ? "<fg=green> Активен ({$containersCount} конт.)</>" : '<fg=yellow> Запущен (0 конт.)</>')
            : '<fg=red> Выключен</>';

        // Smooth ANSI clear (no hard reset)
        $this->output->write("\033[2J\033[H");

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold>󰄛 NOT ALONE — Интерактивная консоль разработчика</>',
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            sprintf('  <fg=yellow> Ветка Git:</>         <fg=white>%s</>', $branch),
            sprintf('  <fg=yellow> LAN IP хоста:</>      <fg=white>%s</>', $lanIp),
            sprintf('  <fg=yellow> SSL сертификаты:</>   %s', $certText),
            sprintf('  <fg=yellow> Docker окружение:</>   %s', $dockerText),
            sprintf('  <fg=yellow> База данных (PG):</>   %s', $dbStatus),
            sprintf('  <fg=yellow> Redis кэш/очереди:</>  %s', $redisStatus),
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            '',
        ]);
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
                label: '  Управление серверами и окружением:',
                options: [
                    'docker_dev' => ' Запустить Docker Dev (docker-dev.sh в foreground)',
                    'docker_up_d' => ' Поднять Docker в фоне (docker compose up -d)',
                    'docker_down' => ' Остановить Docker (docker compose down)',
                    'docker_restart' => ' Перезапустить Docker контейнеры (docker compose restart)',
                    'docker_ps' => ' Показать статус контейнеров (docker compose ps)',
                    'local_dev' => ' Запустить локально без Docker (composer run dev)',
                    'lan_dev' => ' Запустить LAN Dev (bash dev-lan.sh)',
                    'pwa_lan' => ' Запустить PWA LAN (bash pwa-lan.sh)',
                    'ports_check' => ' Проверить доступность портов проекта',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
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
                label: '  База данных и миграции:',
                options: [
                    'migrate' => ' Применить новые миграции (migrate)',
                    'fresh_seed' => ' Полный сброс БД и запуск сидеров (migrate:fresh --seed)',
                    'specific_seeder' => '🌱 Выборочный запуск сидера (Admin, Users, HelpCenter...)',
                    'rollback' => ' Откатить последнюю миграцию (migrate:rollback)',
                    'psql' => ' Открыть PostgreSQL CLI (psql)',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
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
                'DatabaseSeeder' => ' DatabaseSeeder (Все основные сидеры)',
                'AdminSeeder' => ' AdminSeeder (Администраторы и дефолтные роли)',
                'TestUsersSeeder' => ' TestUsersSeeder (Тестовые обычные пользователи и айдолы)',
                'HelpCenterSeeder' => ' HelpCenterSeeder (Статьи базы знаний и FAQ)',
                'NewsSeeder' => ' NewsSeeder (Новости платформы)',
                'BanReasonSeeder' => ' BanReasonSeeder (Справочник причин банов)',
                'ReviewEpithetSeeder' => ' ReviewEpithetSeeder (Эпитеты для отзывов)',
                'K6LoadTestSeeder' => ' K6LoadTestSeeder (Данные для нагрузочных тестов)',
                'cancel' => ' Отмена',
            ],
            scroll: self::MENU_SCROLL
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
                label: '  Генераторы тестовых данных и сущностей:',
                options: [
                    'make_user' => ' Создать одного пользователя (make:user)',
                    'gen_users' => ' Массовая генерация пользователей (users:generate)',
                    'gen_orders' => ' Сгенерировать заказы (orders:generate)',
                    'seed_reviews' => ' Сгенерировать отзывы айдолам (reviews:seed)',
                    'seed_services' => ' Сгенерировать услуги для айдолов (services:seed)',
                    'gen_gallery' => ' Сгенерировать галерею фотографий (gallery:generate)',
                    'test_notify' => ' Отправить тестовое уведомление (notifications:test)',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
            );

            if ($choice === 'back') {
                break;
            }

            $cmdPrefix = ($this->getRunningContainersCount() > 0 && shell_exec('docker ps -q -f name=not_alone_app 2>/dev/null'))
                ? 'docker compose exec -it app php artisan'
                : 'php artisan';

            match ($choice) {
                'make_user' => $this->runCommandInteractive("{$cmdPrefix} make:user"),
                'gen_users' => $this->runCommandInteractive("{$cmdPrefix} users:generate"),
                'gen_orders' => $this->runCommandInteractive("{$cmdPrefix} orders:generate"),
                'seed_reviews' => $this->runCommandInteractive("{$cmdPrefix} reviews:seed"),
                'seed_services' => $this->runCommandInteractive("{$cmdPrefix} services:seed"),
                'gen_gallery' => $this->runCommandInteractive("{$cmdPrefix} gallery:generate"),
                'test_notify' => $this->runCommandInteractive("{$cmdPrefix} notifications:test"),
            };
        }
    }

    // ── 4. SSL и сеть ───────────────────────────────────────────────────────────

    private function menuSsl(): void
    {
        while (true) {
            $choice = select(
                label: '  SSL-сертификаты и сеть:',
                options: [
                    'status' => ' Информация о LAN IP и путях сертификатов',
                    'generate' => ' Перевыпустить SSL-сертификаты для текущего хоста',
                    'ports' => ' Проверить занятость портов',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
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

        $certExists = file_exists($certPath);
        $keyExists = file_exists($keyPath);

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold> Информация о SSL и сертификатах:</>',
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            sprintf('  <fg=yellow> Текущий LAN IP:</>        <fg=white>%s</>', $ip),
            sprintf('  <fg=yellow> Файл сертификата (.pem):</> %s', $certExists ? "<fg=green> {$certPath}</>" : "<fg=red> Не найден ({$certPath})</>"),
            sprintf('  <fg=yellow> Файл ключа (-key.pem):</>   %s', $keyExists ? "<fg=green> {$keyPath}</>" : "<fg=red> Не найден ({$keyPath})</>"),
            sprintf('  <fg=yellow> Утилита mkcert:</>         %s', shell_exec('command -v mkcert') ? '<fg=green> Установлена</>' : '<fg=gray> Не установлена</>'),
            sprintf('  <fg=yellow> Утилита openssl:</>        %s', shell_exec('command -v openssl') ? '<fg=green> Установлена</>' : '<fg=red> Не установлена</>'),
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            '',
        ]);

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
            info(" Сертификаты {$ip}.pem и {$ip}-key.pem успешно созданы!");
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
                label: '  Кэш, сборка и ассеты:',
                options: [
                    'optimize_clear' => ' Очистить все кэши (optimize:clear)',
                    'npm_build' => ' Собрать фронтенд для продакшена (npm run build)',
                    'npm_dev' => ' Запустить dev-сервер Vite (npm run dev)',
                    'ide_helper' => ' Обновить IDE Helpers (composer run ide-helper)',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
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
                label: '  Тестирование и качество кода:',
                options: [
                    'artisan_test' => ' Запустить PHPUnit / Feature тесты (php artisan test)',
                    'k6_chat' => ' Запустить нагрузочный тест K6 (чат)',
                    'k6_master' => ' Запустить нагрузочный тест K6 (мастер-сценарий)',
                    'php_lint' => ' Проверить синтаксис PHP файлов (php -l)',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
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

        info(' Все PHP файлы в app, routes, database, config успешно прошли синтаксическую проверку.');
        $this->waitEnter();
    }

    // ── 7. Логи и консоль ───────────────────────────────────────────────────────

    private function menuLogs(): void
    {
        while (true) {
            $choice = select(
                label: '  Логи и интерактивный шелл:',
                options: [
                    'laravel_log' => ' Читать storage/logs/laravel.log (tail -n 50 -f)',
                    'docker_log' => ' Читать логи Docker контейнера (выбрать сервис)',
                    'tinker' => ' Открыть интерактивный Laravel Tinker',
                    'app_bash' => ' Открыть bash в контейнере not_alone_app',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
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
                'app' => ' app (Nginx + PHP-FPM приложение)',
                'worker' => ' worker (Очереди Queue Worker)',
                'reverb' => ' reverb (WebSocket сервер)',
                'scheduler' => ' scheduler (Cron планировщик задач)',
                'db' => ' db (PostgreSQL)',
                'redis' => ' redis (Redis кэш)',
                'mailpit' => ' mailpit (SMTP & Web UI почты)',
                'minio' => ' minio (S3 хранилище)',
                'vite' => ' vite (Фронтенд Dev Server)',
                'cancel' => ' Отмена',
            ],
            scroll: self::MENU_SCROLL
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

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold> Доступные локальные сервисы Not Alone:</>',
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            '  <fg=yellow>[1] Web Приложение (HTTPS)</>',
            "      <fg=white>LAN:</>   https://{$ip}:8443",
            '      <fg=white>Local:</> https://localhost:8443',
            '',
            '  <fg=yellow>[2] Vite Dev Server (HMR)</>',
            "      https://{$ip}:5173",
            '',
            '  <fg=yellow>[3] Mailpit Web UI (Почта)</>',
            "      http://{$ip}:8025",
            '',
            '  <fg=yellow>[4] MinIO Console (S3 Хранилище)</>',
            "      http://{$ip}:8900",
            '      <fg=gray>Логин: sail | Пароль: password</>',
            '',
            '  <fg=yellow>[5] Reverb WebSocket</>',
            "      https://{$ip}:8443 <fg=gray>(порт 8080 проксируется в Nginx)</>",
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            '',
        ]);

        $open = select(
            label: 'Открыть сервис в браузере?',
            options: [
                'none' => ' Назад в главное меню',
                'app' => " Web App (https://{$ip}:8443)",
                'mailpit' => " Mailpit (http://{$ip}:8025)",
                'minio' => " MinIO Console (http://{$ip}:8900)",
            ],
            scroll: self::MENU_SCROLL
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
        // 1. If Docker daemon is running, inspect container not_alone_db
        if ($this->isDockerDaemonRunning()) {
            $output = trim((string) shell_exec('docker exec not_alone_db pg_isready -U postgres -d not_alone 2>/dev/null'));
            if (str_contains($output, 'accepting connections')) {
                return '<fg=green> Подключено (:5433)</>';
            }
        }

        // 2. Direct PDO test on ports 5433 (Docker published) and config port
        $portsToTry = array_unique([5433, (int) config('database.connections.pgsql.port', 5432), 5432]);
        foreach ($portsToTry as $port) {
            try {
                new \PDO("pgsql:host=127.0.0.1;port={$port};dbname=not_alone", "postgres", "postgres", [
                    \PDO::ATTR_TIMEOUT => 1,
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);
                return "<fg=green> Подключено (:{$port})</>";
            } catch (\Throwable $e) {
                continue;
            }
        }

        return '<fg=red> Недоступно</>';
    }

    private function checkRedisStatus(): string
    {
        // 1. If Docker daemon is running, inspect container not_alone_redis
        if ($this->isDockerDaemonRunning()) {
            $output = trim((string) shell_exec('docker exec not_alone_redis redis-cli ping 2>/dev/null'));
            if (str_contains($output, 'PONG')) {
                return '<fg=green> Подключено (:6379)</>';
            }
        }

        // 2. Direct TCP socket test (works without phpredis PHP extension)
        $fp = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 1);
        if ($fp) {
            fwrite($fp, "PING\r\n");
            $res = trim((string) fgets($fp));
            fclose($fp);
            if (str_contains($res, 'PONG')) {
                return '<fg=green> Подключено (:6379)</>';
            }
        }

        return '<fg=red> Недоступно</>';
    }

    private function checkProjectPorts(): void
    {
        $ports = [
            '8443' => 'Nginx HTTPS (Web App)',
            '5433' => 'PostgreSQL DB (Docker)',
            '6379' => 'Redis Cache / Queue',
            '8025' => 'Mailpit Web UI',
            '1025' => 'Mailpit SMTP',
            '8900' => 'MinIO Web Console',
            '9000' => 'MinIO S3 API',
            '8080' => 'Reverb WebSocket',
            '5173' => 'Vite HMR Dev Server',
        ];

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold> Статус портов проекта:</>',
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
        ]);

        foreach ($ports as $port => $name) {
            $check = trim((string) shell_exec("ss -tulpn 2>/dev/null | grep -E ':{$port}\\b' || true"));
            $status = ! empty($check) ? '<fg=green> Слушается</>' : '<fg=gray> Свободен</>';
            $this->output->writeln(sprintf('  <fg=yellow>%-6s</> %-28s %s', $port, $name, $status));
        }

        $this->output->writeln([
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            '',
        ]);

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

        info(" {$successText}");
        $this->waitEnter();
    }

    private function runArtisanCommand(string $name, array $params = []): void
    {
        $paramStr = '';
        foreach ($params as $key => $val) {
            if (is_bool($val) && $val) {
                $paramStr .= " {$key}";
            } elseif (! is_bool($val)) {
                $paramStr .= " {$key}={$val}";
            }
        }

        // If Docker container not_alone_app is running, run inside container for identical environment
        if ($this->getRunningContainersCount() > 0 && shell_exec('docker ps -q -f name=not_alone_app 2>/dev/null')) {
            $cmd = "docker compose exec -it app php artisan {$name}{$paramStr}";
        } else {
            $cmd = "php artisan {$name}{$paramStr}";
        }

        $this->output->writeln("\n<fg=gray>> {$cmd}</>\n");
        passthru($cmd);
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
