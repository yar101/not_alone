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
            $table->dropColumn('active_frame_path');
            $table->foreignId('active_frame_id')->nullable()->after('avatar_path')->constrained('avatar_frames')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_frame_id']);
            $table->dropColumn('active_frame_id');
            $table->string('active_frame_path')->nullable()->after('avatar_path');
        });
    }
};
