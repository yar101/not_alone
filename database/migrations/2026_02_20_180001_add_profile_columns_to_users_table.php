<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('about')->nullable()->after('birth_date');
            $table->string('voice_path')->nullable()->after('about');
            $table->string('timezone')->nullable()->after('voice_path');
            $table->timestamp('profile_checklist_snoozed_until')->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['about', 'voice_path', 'timezone', 'profile_checklist_snoozed_until']);
        });
    }
};
