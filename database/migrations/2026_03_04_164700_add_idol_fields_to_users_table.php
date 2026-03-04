<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_idol')->default(false)->after('password');
            $table->timestamp('idol_quiz_cooldown_until')->nullable()->after('is_idol');
            $table->timestamp('idol_quiz_passed_at')->nullable()->after('idol_quiz_cooldown_until');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['is_idol', 'idol_quiz_cooldown_until', 'idol_quiz_passed_at']);
        });
    }
};
