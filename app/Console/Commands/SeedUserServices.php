<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use Illuminate\Console\Command;

class SeedUserServices extends Command
{
    protected $signature = 'services:seed {userId?} {--all : Заполнить всех айдолов}';

    protected $description = 'Создаёт тестовые услуги во всех активных категориях для указанного пользователя (или всех айдолов)';

    public function handle(): void
    {
        $categories = ServiceCategory::where('is_active', true)->orderBy('sort_order')->get();
        $timeUnits  = ServiceTimeUnit::where('is_active', true)->orderBy('sort_order')->get();

        if ($categories->isEmpty()) {
            $this->error('Нет активных категорий услуг.');
            return;
        }

        if ($timeUnits->isEmpty()) {
            $this->error('Нет активных единиц времени.');
            return;
        }

        if ($this->option('all')) {
            $users = User::where('is_idol', true)->get();
            if ($users->isEmpty()) {
                $this->error('Нет айдолов в базе.');
                return;
            }
            $this->line("Айдолов: {$users->count()}");
            if (!$this->confirm('Создать услуги для всех?')) {
                return;
            }
            $bar = $this->output->createProgressBar($users->count());
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->start();
            foreach ($users as $user) {
                $bar->setMessage($user->name);
                $this->seedUser($user, $categories, $timeUnits);
                $bar->advance();
            }
            $bar->setMessage('готово');
            $bar->finish();
            $this->newLine();
            $this->info('Готово.');
        } else {
            $userId = $this->argument('userId');
            if (!$userId) {
                $this->error('Укажите userId или используйте --all.');
                return;
            }
            $user = User::findOrFail($userId);
            $this->line("Пользователь: {$user->name} <{$user->email}>");
            if (!$this->confirm('Продолжить?')) {
                return;
            }
            $bar = $this->output->createProgressBar($categories->count());
            $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
            $bar->start();
            $this->seedUser($user, $categories, $timeUnits, $bar);
            $bar->setMessage('готово');
            $bar->finish();
            $this->newLine();
            $this->info('Готово. Услуг: ' . Service::where('user_id', $user->id)->count());
        }
    }

    private function seedUser(User $user, $categories, $timeUnits, $bar = null): void
    {
        foreach ($categories as $cat) {
            if ($bar) {
                $bar->setMessage($cat->name);
            }
            $pairs = [
                [$timeUnits[0], rand(300, 700)],
                [$timeUnits[min(1, count($timeUnits) - 1)], rand(800, 1500)],
            ];
            foreach ($pairs as [$unit, $price]) {
                $name = "{$cat->name} — {$unit->name}";
                Service::firstOrCreate(
                    ['user_id' => $user->id, 'category_id' => $cat->id, 'name' => $name],
                    ['time_unit_id' => $unit->id, 'price' => $price, 'is_active' => true]
                );
            }
            if ($bar) {
                $bar->advance();
            }
        }
    }
}
