<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pack_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price_paid');
            $table->timestamp('purchased_at')->useCurrent();
            $table->unique(['content_pack_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pack_purchases');
    }
};
