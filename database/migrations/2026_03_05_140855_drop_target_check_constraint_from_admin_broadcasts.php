<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Оригинальный ENUM породил check-constraint, не пускающий значение 'filtered'.
        // Колонка уже VARCHAR(20) — просто дропаем устаревший constraint.
        DB::statement('ALTER TABLE admin_broadcasts DROP CONSTRAINT IF EXISTS admin_broadcasts_target_check');
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE admin_broadcasts ADD CONSTRAINT admin_broadcasts_target_check CHECK (target IN ('all', 'user', 'filtered'))");
    }
};
