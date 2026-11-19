<?php

namespace Database\Seeders;

use App\Models\ServiceTimeUnit;
use Illuminate\Database\Seeder;

class ServiceTimeUnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['ru' => '1 раз',     'en' => '1 time'],
            ['ru' => '15 минут',  'en' => '15 minutes'],
            ['ru' => '30 минут',  'en' => '30 minutes'],
            ['ru' => '1 час',     'en' => '1 hour'],
            ['ru' => '1,5 часа',  'en' => '1.5 hours'],
            ['ru' => '2 часа',    'en' => '2 hours'],
            ['ru' => '1 игра',    'en' => '1 game'],
            ['ru' => '1 серия',   'en' => '1 episode'],
            ['ru' => '1 песня',   'en' => '1 song'],
        ];

        foreach ($units as $i => $name) {
            $unit = ServiceTimeUnit::where('name->ru', $name['ru'])->first();
            if ($unit) {
                $unit->setTranslation('name', 'ru', $name['ru']);
                $unit->setTranslation('name', 'en', $name['en']);
                $unit->sort_order = $i;
                $unit->is_active = true;
                $unit->save();
            } else {
                ServiceTimeUnit::create([
                    'name' => $name,
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
            }
        }
    }
}
