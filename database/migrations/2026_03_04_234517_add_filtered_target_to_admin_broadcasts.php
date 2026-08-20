<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL enum нельзя расширить ALTER TYPE без пересоздания — конвертируем в varchar
        DB::statement('ALTER TABLE admin_broadcasts ALTER COLUMN target TYPE VARCHAR(20)');

        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->jsonb('target_filters')->nullable()->after('target_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('admin_broadcasts', function (Blueprint $table) {
            $table->dropColumn('target_filters');
        });
    }
};
