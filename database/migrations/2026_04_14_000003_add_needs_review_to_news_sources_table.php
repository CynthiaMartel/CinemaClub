<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            // Número de intentos fallidos consecutivos
            $table->unsignedTinyInteger('failed_attempts')->default(0)->after('is_active');
            // Marcada automáticamente si falla 3 veces seguidas
            $table->boolean('needs_review')->default(false)->after('failed_attempts');
            $table->text('last_error')->nullable()->after('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('news_sources', function (Blueprint $table) {
            $table->dropColumn(['failed_attempts', 'needs_review', 'last_error']);
        });
    }
};
