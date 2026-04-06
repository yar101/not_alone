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
        Schema::table('order_disputes', function (Blueprint $table) {
            // 'open' | 'approved' (refunded) | 'rejected' (completed)
            $table->string('status')->default('open')->after('details');
            $table->text('admin_note')->nullable()->after('status');
            $table->timestamp('resolved_at')->nullable()->after('admin_note');
        });
    }

    public function down(): void
    {
        Schema::table('order_disputes', function (Blueprint $table) {
            $table->dropColumn(['status', 'admin_note', 'resolved_at']);
        });
    }
};
