<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_entry_films', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_entry_id');
            $table->unsignedInteger('film_id'); // guarda el ID real del film (referencia a idFilm)
            $table->tinyInteger('order')->nullable(); 
            $table->timestamps();

            // 🔸 Claves foráneas coherentes
            $table->foreign('user_entry_id')->references('id')->on('user_entries')->onDelete('cascade');
            $table->foreign('film_id')->references('idFilm')->on('films')->onDelete('cascade'); // 👈 corregido aquí

            $table->unique(['user_entry_id', 'film_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_entry_films');
    }
};



