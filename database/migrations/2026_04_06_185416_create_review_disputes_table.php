<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('idol_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 250);
            $table->string('status')->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_disputes');
    }
};
