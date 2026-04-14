<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_packs', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('published_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
            $table->dropSoftDeletes();
        });
    }
};
