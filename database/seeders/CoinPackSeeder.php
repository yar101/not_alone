<?php

namespace Database\Seeders;

use App\Models\CoinPack;
use Illuminate\Database\Seeder;

class CoinPackSeeder extends Seeder
{
    /**
     * Seed approved Mandarin BaaS coin packs.
     */
    public function run(): void
    {
        // Hide/archive any old non-standard packs
        CoinPack::whereNotIn('coins', [300.00, 500.00, 700.00, 1000.00])
            ->update(['is_visible' => false]);

        $packs = [
            [
                'coins' => 300.00,
                'is_visible' => true,
                'is_starter' => true,
                'sort_order' => 0,
            ],
            [
                'coins' => 500.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 1,
            ],
            [
                'coins' => 700.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 2,
            ],
            [
                'coins' => 1000.00,
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($packs as $pack) {
            CoinPack::updateOrCreate(
                ['coins' => $pack['coins']],
                $pack
            );
        }
    }
}
