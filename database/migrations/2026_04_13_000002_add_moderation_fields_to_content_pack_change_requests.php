<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->json('flagged_fields')->nullable()->after('pending_price');
            $table->json('field_comments')->nullable()->after('flagged_fields');
            // admin_comment now lives in field_comments; keep for rejected status
        });
    }

    public function down(): void
    {
        Schema::table('content_pack_change_requests', function (Blueprint $table) {
            $table->dropColumn(['flagged_fields', 'field_comments']);
        });
    }
};
