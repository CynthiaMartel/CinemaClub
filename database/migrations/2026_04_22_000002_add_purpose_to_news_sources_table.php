<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            // 'news' = fuente de noticias para el inbox editorial
            // 'events' = fuente de eventos para el Event Manager
            $table->enum('purpose', ['news', 'events'])->default('news')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn('purpose');
        });
    }
};
