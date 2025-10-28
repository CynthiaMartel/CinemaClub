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
            $table->string('title', 255);
            $table->string('directedBy', 255);
            $table->string('genre', 100);
            $table->string('origin_country', 100);
            $table->string('original_language', 100);
            $table->text('overview');
            $table->integer('duration');
            $table->text('castCrew');
            $table->date('release_date');
            $table->string('frame', 225);
            $table->text('awards')->nullable();
            $table->text('nominations')->nullable();
            $table->text('festivals')->nullable();
            $table->float('vote_average');
            $table->float('individualRate');
            $table->float('globalRate');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('films');
    }
}

