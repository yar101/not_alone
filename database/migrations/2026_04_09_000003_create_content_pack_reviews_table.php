<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_pack_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_pack_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('decision'); // approved | has_remarks
            $table->json('flagged_fields')->nullable();    // ["title","description","price"]
            $table->json('field_comments')->nullable();    // {"title": "Слишком расплывчато"}
            $table->json('flagged_photo_ids')->nullable(); // [photo_id, ...]
            $table->json('photo_comments')->nullable();    // {"42": "Низкое качество"}
            $table->unsignedBigInteger('cover_photo_id')->nullable(); // chosen existing photo
            $table->string('cover_upload_path')->nullable();          // admin-uploaded cover
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_pack_reviews');
    }
};
