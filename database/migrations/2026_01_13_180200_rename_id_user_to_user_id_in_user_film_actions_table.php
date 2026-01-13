<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('user_film_actions', function (Blueprint $table) {
            $table->renameColumn('idUser', 'user_id');
            // Si también quieres unificar la película:
            $table->renameColumn('idFilm', 'film_id');
        });
    }

    public function down()
    {
        Schema::table('user_film_actions', function (Blueprint $table) {
            $table->renameColumn('user_id', 'idUser');
            $table->renameColumn('film_id', 'idFilm');
        });
    }
};
