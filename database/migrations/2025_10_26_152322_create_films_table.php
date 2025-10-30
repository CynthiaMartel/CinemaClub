<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFilmsTable extends Migration
{
    public function up()
    {
        Schema::create('films', function (Blueprint $table) {
            $table->increments('idFilm');
            $table->integer('tmdb_id')->nullable()->unique()->comment('Identificador real de la película en TMDb');
            $table->string('wikidata_id', 50)->nullable()->unique()->comment('Identificador único del film en Wikidata (Qxxxx)');
            $table->string('title', 255);
        
            $table->string('genre', 100)->nullable();
            $table->string('original_title',100) ->nullable();
            $table->string('origin_country', 100)->nullable();
            $table->string('original_language', 100)->nullable();
            $table->text('overview')->nullable();
            $table->smallInteger('duration')->unsigned()->nullable();
            $table->date('release_date')->nullable();
            
            $table->string('frame', 225)->nullable();
            
            $table->text('awards')->nullable();
            $table->text('nominations')->nullable();
            $table->text('festivals')->nullable();
           
            $table->unsignedSmallInteger('total_awards')->default(0);
            $table->unsignedSmallInteger('total_nominations')->default(0);
            $table->unsignedSmallInteger('total_festivals')->default(0);

            $table->float('vote_average', 3, 1)->default(0);
            $table->float('individualRate', 3, 1)->default(0);
            $table->float('globalRate', 3, 1)->default(0);

            $table->unsignedInteger('director_id')->nullable(); // Relación con cast_crew

            $table->timestamps();

            $table->foreign('director_id')->references('idPerson')->on('cast_crew')->onDelete('set null');

            $table->unique(['title', 'release_date']); // Para evitar el duplicado de misma película (título y año igual),
        });                                            // pero permitir la inserción de películas con mismoi título (ej: remakes)
    }

    public function down()
    {
        Schema::dropIfExists('films');
    }
}



