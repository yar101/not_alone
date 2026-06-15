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
        Schema::create('service_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->json('changed_fields');
            $table->json('pending_name')->nullable();
            $table->unsignedInteger('pending_price')->nullable();
            $table->foreignId('pending_category_id')->nullable()->constrained('service_categories')->nullOnDelete();
            $table->foreignId('pending_time_unit_id')->nullable()->constrained('service_time_units')->nullOnDelete();
            
            $table->string('status')->default('pending'); // pending, has_remarks, approved, rejected
            
            $table->json('flagged_fields')->nullable();
            $table->json('field_comments')->nullable();
            
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_comment')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_change_requests');
    }
};
