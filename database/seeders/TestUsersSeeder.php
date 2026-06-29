<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('123123');

        for ($i = 1; $i <= 20; $i++) {
            $isIdol = $i % 2 === 0;
            
            User::firstOrCreate(
                ['email' => "u{$i}@test.com"],
                [
                    'name' => "u{$i}" . ($isIdol ? ' (Idol)' : ''),
                    'password' => $password,
                    'is_idol' => $isIdol,
                    'email_verified_at' => now(),
                ]
            );
        }
    }
}
