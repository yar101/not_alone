<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // For PostgreSQL, we need to drop the old check constraint and add a new one
        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_status_check');
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_status_check CHECK (status = ANY (ARRAY['pending', 'approved', 'rejected', 'has_remarks']::text[]))");

        Schema::table('services', function (Blueprint $table) {
            $table->timestamp('resubmitted_at')->nullable()->after('moderated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('resubmitted_at');
        });

        DB::statement('ALTER TABLE services DROP CONSTRAINT IF EXISTS services_status_check');
        DB::statement("ALTER TABLE services ADD CONSTRAINT services_status_check CHECK (status = ANY (ARRAY['pending', 'approved', 'rejected']::text[]))");
    }
};
