<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('news_sources')->cascadeOnDelete();
            $table->string('title');
            $table->string('original_url', 767)->unique();
            $table->longText('raw_content')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('ai_tags')->nullable();
            // 0-10, null = no procesado aún
            $table->unsignedTinyInteger('ai_relevance_score')->nullable();
            $table->string('ai_suggested_title')->nullable();
            $table->string('ai_category')->nullable();   // festival|produccion|estreno|convocatoria|otro
            $table->json('ai_canarian_entities')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'drafted'])->default('pending');
            // ID del Post creado desde este item (nullable)
            $table->unsignedBigInteger('published_post_id')->nullable();
            $table->timestamp('found_at')->useCurrent();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'found_at']);
            $table->index(['source_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
