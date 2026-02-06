<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('user_film_actions', function (Blueprint $table) {
        $table->timestamp('watched_at')->nullable()->after('rating');
    });


    // Copiamos la fecha actual (updated_at) al nuevo campo watched_at
    // SOLO para las que ya están vistas o puntuadas.
    DB::statement("
        UPDATE user_film_actions 
        SET watched_at = updated_at 
        WHERE watched = 1 OR rating IS NOT NULL
    ");
}

public function down()
{
    Schema::table('user_film_actions', function (Blueprint $table) {
        $table->dropColumn('watched_at');
    });
}
};
