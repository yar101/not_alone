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
            $gender = $isIdol ? 'female' : 'male';
            $user = User::updateOrCreate(
                ['email' => "u{$i}@test.com"],
                [
                    'name' => "u{$i}" . ($isIdol ? ' (Idol)' : ''),
                    'password' => $password,
                    'is_idol' => $isIdol,
                    'gender' => $gender,
                    'email_verified_at' => now(),
                ]
            );

            if ($isIdol) {
                // Generate a pack for this idol
                $pack = \App\Models\ContentPack::firstOrCreate([
                    'user_id' => $user->id,
                    'title' => "Test Pack by {$user->name}",
                ], [
                    'description' => "This is a test pack.",
                    'price' => 500,
                    'status' => 'published',
                    'published_at' => now(),
                ]);

                // Give this pack to user 1 (the first test user) as a purchase, unviewed
                \App\Models\ContentPackPurchase::firstOrCreate([
                    'content_pack_id' => $pack->id,
                    'user_id' => 1,
                ], [
                    'price_paid' => $pack->price,
                    'purchased_at' => now(),
                    'viewed_at' => null,
                ]);
            }
        }
    }
}
