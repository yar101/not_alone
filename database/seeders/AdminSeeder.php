<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::firstOrCreate(
            ['email' => 'ia@not-alone.pw'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('RdNdWht1$1'),
            ]
        );
    }
}
