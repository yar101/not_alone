<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Удаляем пользователей с пробелами в имени
        DB::table('users')->whereRaw("name LIKE '% %'")->delete();

        // Удаляем дубликаты имён (оставляем запись с наибольшим id)
        DB::statement('
            DELETE FROM users
            WHERE id NOT IN (
                SELECT MAX(id) FROM users GROUP BY name
            )
        ');

        Schema::table('users', function (Blueprint $table) {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['name']);
        });
    }
};
