<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\ServiceCategory;
use App\Models\ServiceTimeUnit;
use Illuminate\Database\Seeder;

class ServiceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Platform settings
        PlatformSetting::upsert([
            ['key' => 'rating_low_threshold',    'value' => '30', 'updated_at' => now(), 'created_at' => now()],
            ['key' => 'would_buy_cooldown_days', 'value' => '7',  'updated_at' => now(), 'created_at' => now()],
        ], ['key'], ['value', 'updated_at']);

        // Default categories
        $categories = ['Игры', 'Общение', 'Обучение', 'Творчество', 'Другое'];
        foreach ($categories as $i => $name) {
            ServiceCategory::firstOrCreate(['name' => $name], ['sort_order' => $i, 'is_active' => true]);
        }

        // Default time units
        $units = ['15 минут', '30 минут', '1 час', '1 игра', '1 день'];
        foreach ($units as $i => $name) {
            ServiceTimeUnit::firstOrCreate(['name' => $name], ['sort_order' => $i, 'is_active' => true]);
        }
    }
}
