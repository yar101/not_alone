<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DevDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds for development and testing environments.
     */
    public function run(): void
    {
        // 1. Run core production catalogs, settings and admin
        $this->call(DatabaseSeeder::class);

        // 2. Test users and content packs
        $this->call(TestUsersSeeder::class);

        // 3. Generate test services for all idols across all categories
        Artisan::call('services:seed', [
            '--all' => true,
            '--force' => true,
        ]);

        // 4. Generate domain transaction lifecycle (deposits, orders, payouts, holds)
        $this->call(TransactionSeeder::class);

        // 5. Seed news for the feed
        $this->call(NewsSeeder::class);
    }
}
