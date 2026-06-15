<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pack_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_pack_id')->constrained()->cascadeOnDelete();
            $table->json('changed_fields');
            $table->string('pending_title', 120)->nullable();
            $table->text('pending_description')->nullable();
            $table->unsignedInteger('pending_price')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pack_change_requests');
    }
};
