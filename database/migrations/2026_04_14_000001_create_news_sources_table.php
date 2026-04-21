<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->enum('type', ['rss', 'scraping', 'sitemap'])->default('rss');
            $table->unsignedTinyInteger('check_interval_hours')->default(6);
            $table->timestamp('last_checked_at')->nullable();
            $table->boolean('is_active')->default(true);
            // JSON con selectores CSS para scraping: { "items": ".article", "title": "h2 a", "link": "h2 a", "description": ".summary" }
            $table->json('selector_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_sources');
    }
};
