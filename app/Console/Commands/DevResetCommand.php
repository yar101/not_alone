<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\DevDatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class DevResetCommand extends Command
{
    protected $signature = 'dev:reset {--clean-storage : Очистить тестовые файлы в хранилище (MinIO/S3)} {--force : Выполнить без подтверждения}';

    protected $description = 'Полный сброс dev-окружения: migrate:fresh, DevDatabaseSeeder, очистка кэша и Redis';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('Команда dev:reset запрещена в продакшене без флага --force.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('ВНИМАНИЕ: Это полностью очистит базу данных и пересидирует данные через DevDatabaseSeeder. Продолжить?', false)) {
            $this->info('Операция отменена.');

            return self::SUCCESS;
        }

        $this->warn('1/4 Очистка таблиц и применение миграций (migrate:fresh)...');
        Artisan::call('migrate:fresh', ['--force' => true], $this->output);

        $this->info('2/4 Сидирование тестового окружения (DevDatabaseSeeder)...');
        Artisan::call('db:seed', ['--class' => DevDatabaseSeeder::class, '--force' => true], $this->output);

        $this->info('3/4 Очистка кэша и Redis...');
        Artisan::call('cache:clear', [], $this->output);
        try {
            Redis::connection()->flushall();
            $this->line('  ✓ Redis flushall выполнен успешно.');
        } catch (\Throwable $e) {
            $this->warn('  ⚠ Не удалось очистить Redis: '.$e->getMessage());
        }

        if ($this->option('clean-storage')) {
            $this->info('4/4 Очистка тестового хранилища (MinIO/S3)...');
            try {
                $disk = Storage::disk(config('filesystems.default'));
                $disk->deleteDirectory('content-packs');
                $this->line('  ✓ Директория content-packs в хранилище очищена.');
            } catch (\Throwable $e) {
                $this->warn('  ⚠ Ошибка очистки хранилища: '.$e->getMessage());
            }
        } else {
            $this->line('4/4 Пропуск очистки хранилища (используйте флаг --clean-storage для сброса файлов).');
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('🎉 Dev-окружение Not Alone успешно сброшено и готово!');
        $this->info('====================================================');
        $this->line('  Пользователей: '.User::count().' (Клиенты и Айдолы)');
        $this->line('  Услуг:         '.Service::count());
        $this->line('  Заказов:       '.Order::count());
        $this->newLine();
        $this->line('  Администратор: ia@not-alone.pw / RdNdWht1$1');
        $this->line('  Тестовые юзеры: u1@test.com .. u100@test.com / 123123');
        $this->info('====================================================');

        return self::SUCCESS;
    }
}
