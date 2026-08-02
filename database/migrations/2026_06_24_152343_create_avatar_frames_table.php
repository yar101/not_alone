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
        Schema::create('avatar_frames', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path'); // Путь к картинке
            $table->string('type')->default('free'); // free, paid, achievement
            $table->decimal('price', 10, 2)->default(0); // Стоимость, если платная
            $table->string('condition_class')->nullable(); // Класс условия для достижений
            $table->boolean('is_active')->default(true); // Можно ли получить/купить
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('avatar_frames');
    }
};
