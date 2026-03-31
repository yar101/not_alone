<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('completion_confirmed_by_idol')->default(false)->after('cancelled_by');
            $table->boolean('completion_confirmed_by_customer')->default(false)->after('completion_confirmed_by_idol');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['completion_confirmed_by_idol', 'completion_confirmed_by_customer']);
        });
    }
};
