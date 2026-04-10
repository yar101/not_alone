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
        Schema::table('content_packs', function (Blueprint $table) {
            $table->timestamp('resubmitted_at')->nullable()->after('moderated_at');
        });
    }

    public function down(): void
    {
        Schema::table('content_packs', function (Blueprint $table) {
            $table->dropColumn('resubmitted_at');
        });
    }
};
