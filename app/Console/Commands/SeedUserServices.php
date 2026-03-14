<?php

namespace App\Console\Commands;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use App\Models\User;
use Illuminate\Console\Command;

class SeedUserServices extends Command
{
    protected $signature = 'services:seed {userId}';

    protected $description = 'Создаёт тестовые услуги во всех активных категориях для указанного пользователя';

    public function handle(): void
    {
        $user = User::findOrFail($this->argument('userId'));

        $this->line("Пользователь: {$user->name} <{$user->email}>");

        if (!$this->confirm('Продолжить?')) {
            return;
        }

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

        foreach ($categories as $cat) {
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
        }

        $this->info('Готово. Создано/найдено услуг: ' . Service::where('user_id', $user->id)->count());
    }
}
