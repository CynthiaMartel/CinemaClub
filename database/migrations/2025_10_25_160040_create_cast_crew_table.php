<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCastCrewTable extends Migration
{
    public function up(): void
    {
        Schema::create('cast_crew', function (Blueprint $table) {
            $table->increments('idPerson'); 
            $table->integer('tmdb_id')->unique(); // Para sincronización
            $table->string('name', 255);
            $table->text('bio')->nullable();
            $table->string('profile_path', 255)->nullable();
            $table->date('birthday')->nullable(); // Corregido
            $table->string('place_of_birth', 255)->nullable();
            $table->string('photo', 225)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cast_crew');
    }
}

