<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_film_actions', function (Blueprint $table) {
            // Índice simple en film_id para acelerar recalculateFilmRating()
            // que consulta WHERE film_id = X AND rating IS NOT NULL
            $table->index('film_id', 'idx_user_film_actions_film_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_film_actions', function (Blueprint $table) {
            $table->dropIndex('idx_user_film_actions_film_id');
        });
    }
};
