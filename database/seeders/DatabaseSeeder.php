<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        \$this->call([
            PlatformSettingSeeder::class,
            AdminSeeder::class,
            TraitSeeder::class,
            InterestSeeder::class,
            QuizQuestionSeeder::class,
            ServiceCategorySeeder::class,
            ServiceTimeUnitSeeder::class,
            BanReasonSeeder::class,
            NewsSeeder::class,
            ReviewEpithetSeeder::class,
        ]);
    }
}
