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
        // 1. Comprobamos si la columna YA existe.
        // Si NO existe (!), entramos y la creamos.
        if (!Schema::hasColumn('user_film_actions', 'watched_at')) {
            Schema::table('user_film_actions', function (Blueprint $table) {
                $table->timestamp('watched_at')->nullable()->after('rating');
            });
        }

        // 2. Ejecutamos la actualización de datos.
        // Esto se ejecutará tanto si la acabamos de crear como si ya existía,
        // asegurando que los datos sean consistentes.
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
