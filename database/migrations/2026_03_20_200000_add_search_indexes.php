<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index('gender');
            $table->index('is_idol');
            $table->index('rating');
            $table->index('birth_date');
            $table->index('is_banned');
            $table->index('timezone');
        });

        Schema::table('user_languages', function (Blueprint $table) {
            $table->index('language_code');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index(['category_id', 'status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['gender']);
            $table->dropIndex(['is_idol']);
            $table->dropIndex(['rating']);
            $table->dropIndex(['birth_date']);
            $table->dropIndex(['is_banned']);
            $table->dropIndex(['timezone']);
        });

        Schema::table('user_languages', function (Blueprint $table) {
            $table->dropIndex(['language_code']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status', 'is_active']);
        });
    }
};
