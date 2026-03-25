<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ban_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->enum('type', ['chat_block', 'user_ban']);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ban_reasons');
    }
};
