<?php

namespace Database\Seeders;

use App\Models\ServiceTimeUnit;
use Illuminate\Database\Seeder;

class ServiceTimeUnitSeeder extends Seeder
{
    public function run(): void
    {
        ServiceTimeUnit::truncate();

        $units = [
            '1 раз',
            '15 минут',
            '30 минут',
            '1 час',
            '1,5 часа',
            '2 часа',
            '1 игра',
            '1 серия',
            '1 песня',
        ];

        foreach ($units as $i => $name) {
            ServiceTimeUnit::create([
                'name'       => $name,
                'sort_order' => $i,
                'is_active'  => true,
            ]);
        }
    }
}
