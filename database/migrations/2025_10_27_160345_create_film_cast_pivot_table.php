<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilmCastPivotTable extends Migration
{
    public function up(): void
    {
        Schema::create('film_cast_pivot', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('idFilm');
            $table->unsignedInteger('idPerson');
            $table->string('role', 100); // Actor, Director
            $table->string('character_name', 255)->nullable();
            $table->integer('credit_order')->nullable();

            $table->foreign('idFilm')->references('idFilm')->on('films')->onDelete('cascade');
            $table->foreign('idPerson')->references('idPerson')->on('cast_crew')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('film_cast_pivot'); // Corregido
    }
}

