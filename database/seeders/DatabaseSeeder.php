<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            TraitSeeder::class,
            InterestSeeder::class,
            QuizQuestionSeeder::class,
            ServiceDataSeeder::class,
        ]);
    }
}
