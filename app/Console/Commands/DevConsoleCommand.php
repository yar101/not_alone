<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class DevConsoleCommand extends Command
{
    protected $signature = 'dev:tui {section? : Прямой переход в раздел (docker, env, db, logs...)}';

    protected $description = 'Интерактивная консоль разработчика Not Alone (TUI)';

    private const MENU_SCROLL = 15;

    public function handle(): int
    {
        $initialSection = $this->argument('section');
        if ($initialSection) {
            $this->renderHeader();
            $this->handleSection($initialSection);
        }

        while (true) {
            $this->renderHeader();

            $choice = select(
                label: 'Выберите раздел для работы:',
                options: [
                    'docker' => '  Docker (контейнеры, сервисы, очистка)',
                    'env' => '  Серверы и сеть (Dev, LAN, порты)',
                    'db' => '  База данных (миграции, сидеры, psql)',
                    'generators' => '  Генераторы данных (пользователи, заказы)',
                    'ssl' => '  SSL-сертификаты (генерация, статус)',
                    'cache' => '  Кэш и сборка (optimize, npm build)',
                    'test' => '  Тесты и линтер (phpunit, k6, php -l)',
                    'logs' => '  Логи и шелл (laravel.log, docker, tinker)',
                    'urls' => '  Ссылки на сервисы (Web, Mailpit, MinIO)',
                    'exit' => '  Выход',
                ],
                default: 'docker',
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
        $allContainers = $dockerRunning ? $this->getAllContainers() : [];
        $totalContainers = count($allContainers);
        $otherCount = $totalContainers - count(array_filter($allContainers, fn ($c) => $c['is_project']));
        $dbStatus = $this->checkDbStatus();
        $redisStatus = $this->checkRedisStatus();

        $certText = $certsExist ? '<fg=green> Готовы</>' : '<fg=red> Отсутствуют</>';
        $dockerText = $dockerRunning
            ? ($containersCount > 0
                ? "<fg=green> Активен ({$containersCount} конт.".($otherCount > 0 ? " | <fg=yellow>+{$otherCount} внешних</><fg=green>" : '').')</>'
                : ($totalContainers > 0 ? "<fg=yellow> Запущен ({$totalContainers} внешних/остановленных)</>" : '<fg=yellow> Запущен (0 конт.)</>'))
            : '<fg=red> Выключен</>';

        // Smooth ANSI clear (no hard reset)
        $this->output->write("\033[2J\033[H");

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold>󰄛 NOT ALONE — Панель разработчика</>',
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            sprintf('  <fg=yellow> Git ветка:</>         <fg=white>%s</>', $branch),
            sprintf('  <fg=yellow> LAN IP:</>            <fg=white>%s</>', $lanIp),
            sprintf('  <fg=yellow> SSL:</>               %s', $certText),
            sprintf('  <fg=yellow> Docker:</>            %s', $dockerText),
            sprintf('  <fg=yellow> PostgreSQL:</>        %s', $dbStatus),
            sprintf('  <fg=yellow> Redis:</>             %s', $redisStatus),
            '  <fg=gray>───────────────────────────────────────────────────────────</>',
            '',
        ]);
    }

    private function handleSection(string $section): void
    {
        match ($section) {
            'docker' => $this->menuDocker(),
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

    // ── 0. Docker и контейнеры ───────────────────────────────────────────────────

    private function menuDocker(): void
    {
        while (true) {
            $choice = select(
                label: '  Управление Docker и контейнерами:',
                options: [
                    'status' => '  Статус контейнеров (таблица)',
                    'manage_containers' => '  Управление контейнерами (Stop / Rm / Restart)',
                    'prune_exited' => '  Удалить остановленные контейнеры (container prune)',
                    'prune_system' => '  Очистить dangling-образы и кэш (system prune)',
                    'docker_dev' => '  Запустить в foreground (docker-dev.sh)',
                    'docker_up_d' => '  Запустить в фоне (up -d)',
                    'docker_up_build' => '  Пересобрать и запустить (up -d --build)',
                    'restart_service' => '  Перезапустить сервис (app, db, redis...)',
                    'docker_stop' => '  Остановить проект (compose stop)',
                    'docker_down' => '  Остановить и удалить контейнеры (compose down)',
                    'clean_slate' => '  Полный сброс с томами (down -v)',
                    'service_shell' => '  Открыть Shell в сервисе (app, db, redis...)',
                    'service_logs' => '  Логи сервиса (tail -f)',
                    'system_df' => '  Дисковое пространство (docker system df)',
                    'back' => '  Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
            );

            if ($choice === 'back') {
                break;
            }

            match ($choice) {
                'status' => $this->showDockerStatus(),
                'manage_containers' => $this->manageSpecificContainers(),
                'prune_exited' => $this->pruneExitedContainers(),
                'prune_system' => $this->pruneSystemDocker(),
                'docker_dev' => $this->runCommandInteractive('bash docker-dev.sh'),
                'docker_up_d' => $this->startDockerBackground(false),
                'docker_up_build' => $this->startDockerBackground(true),
                'restart_service' => $this->restartSpecificService(),
                'docker_stop' => $this->stopDockerProject(),
                'docker_down' => $this->downDockerProject(),
                'clean_slate' => $this->cleanSlateProject(),
                'service_shell' => $this->openServiceShell(),
                'service_logs' => $this->selectAndTailDockerLog(),
                'system_df' => $this->runCommandInteractive('docker system df'),
            };
        }
    }

    private function showDockerStatus(): void
    {
        if (! $this->isDockerDaemonRunning()) {
            warning('Docker daemon не запущен.');
            $this->waitEnter();

            return;
        }

        $containers = $this->getAllContainers();
        if (empty($containers)) {
            info('В системе нет Docker контейнеров.');
            $this->waitEnter();

            return;
        }

        $rows = [];
        $projectCount = 0;
        $otherCount = 0;
        $runningCount = 0;
        $exitedCount = 0;

        foreach ($containers as $c) {
            if ($c['is_project']) {
                $projectCount++;
                $badge = '<fg=cyan>Проект</>';
            } else {
                $otherCount++;
                $badge = '<fg=gray>Внешний</>';
            }

            if ($c['State'] === 'running') {
                $runningCount++;
                $statusFormatted = '<fg=green> '.$c['Status'].'</>';
            } elseif ($c['State'] === 'exited') {
                $exitedCount++;
                $statusFormatted = '<fg=red> '.$c['Status'].'</>';
            } else {
                $statusFormatted = '<fg=yellow>◐ '.$c['Status'].'</>';
            }

            $ports = $c['Ports'];
            $ports = str_replace(['0.0.0.0:', '[::]:'], [':', ':'], $ports);
            if (mb_strlen($ports) > 32) {
                $ports = mb_strimwidth($ports, 0, 30, '...');
            }

            $image = mb_strimwidth($c['Image'], 0, 26, '...');

            $rows[] = [
                $c['Names'],
                $badge,
                $statusFormatted,
                $image,
                $ports ?: '<fg=gray>-</>',
            ];
        }

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold> Контейнеры Docker:</>',
        ]);

        table(['Контейнер', 'Тип', 'Статус', 'Образ', 'Порты'], $rows);

        $this->output->writeln([
            sprintf(
                '  Всего: <fg=white>%d</> │ Запущено: <fg=green>%d</> │ Остановлено: <fg=red>%d</> │ Проект: <fg=cyan>%d</> │ Внешних: <fg=yellow>%d</>',
                count($containers),
                $runningCount,
                $exitedCount,
                $projectCount,
                $otherCount
            ),
            '',
        ]);

        if ($exitedCount > 0) {
            info(" Остановленных контейнеров: {$exitedCount}. Для очистки выберите пункт 'Удалить остановленные контейнеры'.");
        }

        $this->waitEnter();
    }

    private function manageSpecificContainers(): void
    {
        if (! $this->isDockerDaemonRunning()) {
            warning('Docker daemon не запущен.');
            $this->waitEnter();

            return;
        }

        $containers = $this->getAllContainers();
        if (empty($containers)) {
            info('В системе нет Docker контейнеров.');
            $this->waitEnter();

            return;
        }

        $options = [];
        foreach ($containers as $c) {
            $tag = $c['is_project'] ? '[Проект]' : '[Внешний]';
            $stateIcon = $c['State'] === 'running' ? '' : '';
            $name = mb_strimwidth($c['Names'], 0, 24, '...');
            $status = mb_strimwidth($c['Status'], 0, 18, '...');
            $image = mb_strimwidth($c['Image'], 0, 24, '...');

            $label = sprintf(
                '%s %s %s │ %s │ %s',
                mb_str_pad($tag, 10),
                $stateIcon,
                mb_str_pad($name, 24),
                mb_str_pad($status, 18),
                $image
            );
            $options[$c['ID']] = $label;
        }

        $selected = multiselect(
            label: 'Выберите контейнеры для действия (пробел — выбор, enter — подтвердить):',
            options: $options,
            scroll: self::MENU_SCROLL
        );

        if (empty($selected)) {
            info('Контейнеры не выбраны.');

            return;
        }

        $count = count($selected);
        $action = select(
            label: "Выбрано контейнеров: {$count}. Действие:",
            options: [
                'stop' => ' Остановить (docker stop)',
                'restart' => ' Перезапустить (docker restart)',
                'rm' => ' Удалить принудительно (docker rm -f)',
                'cancel' => ' Отмена',
            ]
        );

        if ($action === 'cancel') {
            return;
        }

        $ids = implode(' ', array_map('escapeshellarg', $selected));

        if ($action === 'rm') {
            if (confirm("Удалить выбранные контейнеры ({$count} шт.)?", default: false)) {
                $this->runCommandWithSpinner("docker rm -f {$ids}", 'Удаление контейнеров...', 'Контейнеры удалены.');
            }
        } elseif ($action === 'stop') {
            $this->runCommandWithSpinner("docker stop {$ids}", 'Остановка контейнеров...', 'Контейнеры остановлены.');
        } elseif ($action === 'restart') {
            $this->runCommandWithSpinner("docker restart {$ids}", 'Перезапуск контейнеров...', 'Контейнеры перезапущены.');
        }
    }

    private function pruneExitedContainers(): void
    {
        if (confirm('Удалить все остановленные контейнеры (docker container prune -f)?', default: true)) {
            $this->runCommandWithSpinner('docker container prune -f', 'Очистка контейнеров...', 'Остановленные контейнеры удалены.');
        }
    }

    private function pruneSystemDocker(): void
    {
        if (confirm('Очистить неиспользуемые dangling-образы и кэш сборщика Docker?', default: true)) {
            $this->runCommandWithSpinner(
                'docker image prune -f && docker builder prune -f',
                'Очистка образов и кэша Docker...',
                'Образы и кэш сборщика успешно очищены.'
            );
        }
    }

    private function startDockerBackground(bool $build = false): void
    {
        $ip = $this->getLanIp();
        $this->ensureSslCertificatesExist($ip);

        $flag = $build ? ' --build' : '';
        $this->runCommandWithSpinner(
            "DEV_HOST={$ip} docker compose up -d{$flag} --remove-orphans",
            $build ? 'Сборка и запуск контейнеров в фоне...' : 'Запуск контейнеров Docker в фоне...',
            'Контейнеры проекта Not Alone успешно запущены.'
        );
    }

    private function stopDockerProject(): void
    {
        $this->runCommandWithSpinner(
            'docker compose stop',
            'Остановка контейнеров Not Alone...',
            'Контейнеры проекта Not Alone остановлены.'
        );
    }

    private function downDockerProject(): void
    {
        $ip = $this->getLanIp();
        $this->runCommandWithSpinner(
            "DEV_HOST={$ip} docker compose down --remove-orphans",
            'Остановка и удаление контейнеров Not Alone...',
            'Контейнеры проекта Not Alone и сиротские контейнеры удалены.'
        );
    }

    private function cleanSlateProject(): void
    {
        $confirmed = confirm(
            label: 'Сбросить проект с удалением всех томов данных (БД, S3, Redis)?',
            default: false
        );

        if ($confirmed) {
            $ip = $this->getLanIp();
            $this->runCommandWithSpinner(
                "DEV_HOST={$ip} docker compose down -v --remove-orphans",
                'Полный сброс окружения проекта...',
                'Окружение и тома данных успешно удалены.'
            );
        }
    }

    private function restartSpecificService(): void
    {
        $service = select(
            label: 'Выберите сервис для перезапуска:',
            options: [
                'all' => ' Все сервисы (compose restart)',
                'app' => ' app (Nginx + PHP-FPM)',
                'worker' => ' worker (Queue Worker)',
                'reverb' => ' reverb (WebSocket)',
                'scheduler' => ' scheduler (Cron)',
                'db' => ' db (PostgreSQL)',
                'redis' => ' redis',
                'vite' => ' vite (Frontend Dev Server)',
                'mailpit' => ' mailpit',
                'minio' => ' minio (S3 Storage)',
                'cancel' => ' Отмена',
            ],
            scroll: self::MENU_SCROLL
        );

        if ($service === 'cancel') {
            return;
        }

        $ip = $this->getLanIp();
        if ($service === 'all') {
            $this->runCommandWithSpinner("DEV_HOST={$ip} docker compose restart", 'Перезапуск всех сервисов...', 'Все сервисы перезапущены.');
        } else {
            $this->runCommandWithSpinner("DEV_HOST={$ip} docker compose restart {$service}", "Перезапуск сервиса {$service}...", "Сервис {$service} успешно перезапущен.");
        }
    }

    private function openServiceShell(): void
    {
        $service = select(
            label: 'Выберите сервис для входа в Shell / CLI:',
            options: [
                'app' => ' app (bash)',
                'db' => ' db (psql)',
                'redis' => ' redis (redis-cli)',
                'worker' => ' worker (sh)',
                'reverb' => ' reverb (sh)',
                'vite' => ' vite (sh)',
                'minio' => ' minio (sh)',
                'custom' => ' Выбрать запущенный контейнер из списка',
                'cancel' => ' Отмена',
            ],
            scroll: self::MENU_SCROLL
        );

        if ($service === 'cancel') {
            return;
        }

        if ($service === 'custom') {
            $running = array_filter($this->getAllContainers(), fn ($c) => $c['State'] === 'running');
            if (empty($running)) {
                warning('Нет запущенных контейнеров.');
                $this->waitEnter();

                return;
            }

            $options = [];
            foreach ($running as $c) {
                $options[$c['Names']] = sprintf('%-25s (%s)', $c['Names'], $c['Image']);
            }
            $options['cancel'] = ' Отмена';

            $target = select(label: 'Выберите запущенный контейнер:', options: $options, scroll: self::MENU_SCROLL);
            if ($target !== 'cancel') {
                $this->runCommandInteractive("docker exec -it {$target} sh");
            }

            return;
        }

        match ($service) {
            'app' => $this->runCommandInteractive('docker compose exec -it app bash'),
            'db' => $this->runCommandInteractive('docker compose exec -it db psql -U postgres -d not_alone'),
            'redis' => $this->runCommandInteractive('docker compose exec -it redis redis-cli'),
            default => $this->runCommandInteractive("docker compose exec -it {$service} sh"),
        };
    }

    private function getAllContainers(): array
    {
        if (! $this->isDockerDaemonRunning()) {
            return [];
        }

        $raw = shell_exec('docker ps -a --format "{{json .}}" 2>/dev/null');
        if (empty(trim((string) $raw))) {
            return [];
        }

        $containers = [];
        $lines = explode("\n", trim((string) $raw));
        foreach ($lines as $line) {
            $data = json_decode($line, true);
            if (! is_array($data) || empty($data['ID'])) {
                continue;
            }

            $name = $data['Names'] ?? $data['ID'];
            $isProject = str_starts_with($name, 'not_alone');

            $containers[] = [
                'ID' => $data['ID'],
                'Names' => $name,
                'Image' => $data['Image'] ?? '-',
                'State' => strtolower($data['State'] ?? 'unknown'),
                'Status' => $data['Status'] ?? '-',
                'Ports' => $data['Ports'] ?? '-',
                'is_project' => $isProject,
            ];
        }

        return $containers;
    }

    private function ensureSslCertificatesExist(string $ip): void
    {
        $certPath = base_path("{$ip}.pem");
        $keyPath = base_path("{$ip}-key.pem");

        if (file_exists($certPath) && file_exists($keyPath)) {
            return;
        }

        if (shell_exec('command -v mkcert')) {
            shell_exec("mkcert -key-file '{$keyPath}' -cert-file '{$certPath}' '{$ip}' 'localhost' 127.0.0.1 ::1 2>/dev/null");
        } elseif (shell_exec('command -v openssl')) {
            shell_exec("openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout '{$keyPath}' -out '{$certPath}' -subj '/CN={$ip}' -addext 'subjectAltName=IP:{$ip},IP:127.0.0.1,DNS:localhost' 2>/dev/null");
        }
    }

    // ── 1. Серверы и окружение ───────────────────────────────────────────────────

    private function menuEnvironment(): void
    {
        while (true) {
            $choice = select(
                label: '  Управление серверами и окружением:',
                options: [
                    'docker_menu' => ' Меню Docker (контейнеры, сервисы)',
                    'docker_dev' => ' Docker Dev в foreground (docker-dev.sh)',
                    'docker_up_d' => ' Docker в фоне (compose up -d)',
                    'docker_down' => ' Остановить Docker (compose down)',
                    'docker_restart' => ' Перезапустить контейнеры (restart)',
                    'docker_ps' => ' Статус контейнеров (compose ps)',
                    'local_dev' => ' Локальный запуск без Docker (composer run dev)',
                    'lan_dev' => ' LAN Dev сервер (dev-lan.sh)',
                    'pwa_lan' => ' PWA LAN сервер (pwa-lan.sh)',
                    'ports_check' => ' Проверка портов проекта',
                    'back' => ' Назад в главное меню',
                ],
                scroll: self::MENU_SCROLL
            );

            if ($choice === 'back') {
                break;
            }

            $ip = $this->getLanIp();

            match ($choice) {
                'docker_menu' => $this->menuDocker(),
                'docker_dev' => $this->runCommandInteractive('bash docker-dev.sh'),
                'docker_up_d' => $this->startDockerBackground(false),
                'docker_down' => $this->downDockerProject(),
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
                    'migrate' => ' Применить миграции (migrate)',
                    'fresh_seed' => ' Сбросить БД и засеять (migrate:fresh --seed)',
                    'specific_seeder' => ' Выборочный запуск сидера',
                    'rollback' => ' Откатить миграцию (migrate:rollback)',
                    'psql' => ' Консоль PostgreSQL (psql)',
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
                label: '  Генераторы тестовых данных:',
                options: [
                    'make_user' => ' Создать пользователя (make:user)',
                    'gen_users' => ' Сгенерировать пользователей (users:generate)',
                    'gen_orders' => ' Сгенерировать заказы (orders:generate)',
                    'seed_reviews' => ' Сгенерировать отзывы (reviews:seed)',
                    'seed_services' => ' Сгенерировать услуги (services:seed)',
                    'gen_gallery' => ' Сгенерировать галерею (gallery:generate)',
                    'test_notify' => ' Тестовое уведомление (notifications:test)',
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
                    'status' => ' Статус SSL и LAN IP',
                    'generate' => ' Перевыпустить SSL-сертификаты',
                    'ports' => ' Проверить доступность портов',
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
            sprintf('  <fg=yellow> LAN IP:</>            <fg=white>%s</>', $ip),
            sprintf('  <fg=yellow> Сертификат (.pem):</>  %s', $certExists ? "<fg=green> {$certPath}</>" : "<fg=red> Не найден ({$certPath})</>"),
            sprintf('  <fg=yellow> Ключ (-key.pem):</>    %s', $keyExists ? "<fg=green> {$keyPath}</>" : "<fg=red> Не найден ({$keyPath})</>"),
            sprintf('  <fg=yellow> mkcert:</>             %s', shell_exec('command -v mkcert') ? '<fg=green> Установлена</>' : '<fg=gray> Не установлена</>'),
            sprintf('  <fg=yellow> openssl:</>            %s', shell_exec('command -v openssl') ? '<fg=green> Установлена</>' : '<fg=red> Не установлена</>'),
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
                    'npm_build' => ' Собрать фронтенд (npm run build)',
                    'npm_dev' => ' Dev-сервер Vite (npm run dev)',
                    'ide_helper' => ' Обновить IDE Helpers (ide-helper)',
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
                    'artisan_test' => ' Тесты PHPUnit (artisan test)',
                    'k6_chat' => ' Нагрузочный тест K6 (чат)',
                    'k6_master' => ' Нагрузочный тест K6 (мастер)',
                    'php_lint' => ' Проверка синтаксиса PHP (php -l)',
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

        info(' Все PHP файлы в app, routes, database, config успешно прошли проверку.');
        $this->waitEnter();
    }

    // ── 7. Логи и консоль ───────────────────────────────────────────────────────

    private function menuLogs(): void
    {
        while (true) {
            $choice = select(
                label: '  Логи и интерактивный шелл:',
                options: [
                    'laravel_log' => ' Лог приложения (laravel.log)',
                    'docker_log' => ' Логи Docker сервиса',
                    'tinker' => ' Консоль Laravel Tinker',
                    'app_bash' => ' Bash в контейнере app',
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
                'app' => ' app (Nginx + PHP-FPM)',
                'worker' => ' worker (Queue Worker)',
                'reverb' => ' reverb (WebSocket)',
                'scheduler' => ' scheduler (Cron)',
                'db' => ' db (PostgreSQL)',
                'redis' => ' redis',
                'mailpit' => ' mailpit (Почта)',
                'minio' => ' minio (S3 Хранилище)',
                'vite' => ' vite (Frontend Dev Server)',
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

        $rows = [
            ['Web App (HTTPS)', "https://{$ip}:8443", 'Local: https://localhost:8443'],
            ['Vite Dev Server', "https://{$ip}:5173", 'HMR Dev Server'],
            ['Mailpit Web UI', "http://{$ip}:8025", 'SMTP: :1025'],
            ['MinIO Console', "http://{$ip}:8900", 'Логин: sail | Пароль: password'],
            ['Reverb WebSocket', "https://{$ip}:8443", 'Проксируется в Nginx (:8080)'],
        ];

        $this->output->writeln([
            '',
            '  <fg=cyan;options=bold> Сервисы Not Alone:</>',
        ]);

        table(['Сервис', 'URL', 'Инфо'], $rows);

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
                new \PDO("pgsql:host=127.0.0.1;port={$port};dbname=not_alone", 'postgres', 'postgres', [
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
        ]);

        $rows = [];
        foreach ($ports as $port => $name) {
            $check = trim((string) shell_exec("ss -tulpn 2>/dev/null | grep -E ':{$port}\\b' || true"));
            $status = ! empty($check) ? '<fg=green> Занят (активен)</>' : '<fg=gray> Свободен</>';
            $rows[] = [$port, $name, $status];
        }

        table(['Порт', 'Сервис', 'Статус'], $rows);

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
