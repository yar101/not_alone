<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_traits', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trait_id')->constrained('traits')->cascadeOnDelete();
            $table->primary(['user_id', 'trait_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_traits');
    }
};
