<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_film_actions', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('idUser');
        $table->unsignedInteger('idFilm');
        $table->foreign('idUser')->references('id')->on('users')->onDelete('cascade');
        $table->foreign('idFilm')->references('idFilm')->on('films')->onDelete('cascade');
        $table->boolean('is_favorite')->default(false);
        $table->boolean('watch_later')->default(false);
        $table->boolean('watched')->default(false);
        $table->tinyInteger('rating')->unsigned()->nullable();
        $table->string('short_review', 500)->nullable();
        $table->enum('visibility', ['public', 'friends', 'private'])->default('public');
        $table->timestamps();
        $table->unique(['idUser', 'idFilm']);
    });

    }

    public function down(): void
    {
        Schema::dropIfExists('user_film_actions');
    }
};
