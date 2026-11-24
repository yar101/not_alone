<?php

use App\Models\CoinPack;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hide/archive any old non-standard packs
        CoinPack::whereNotIn('coins', [300.00, 500.00, 700.00, 1000.00])
            ->update(['is_visible' => false]);

        // 1. Starter pack: 300 🪙 (300 ₽), one-time only
        CoinPack::updateOrCreate(
            ['coins' => 300.00],
            [
                'is_visible' => true,
                'is_starter' => true,
                'sort_order' => 0,
            ]
        );

        // 2. Regular pack 1: 500 🪙 (500 ₽)
        CoinPack::updateOrCreate(
            ['coins' => 500.00],
            [
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 1,
            ]
        );

        // 3. Regular pack 2: 700 🪙 (700 ₽)
        CoinPack::updateOrCreate(
            ['coins' => 700.00],
            [
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 2,
            ]
        );

        // 4. Regular pack 3: 1 000 🪙 (1 000 ₽)
        CoinPack::updateOrCreate(
            ['coins' => 1000.00],
            [
                'is_visible' => true,
                'is_starter' => false,
                'sort_order' => 3,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op rollback
    }
};
