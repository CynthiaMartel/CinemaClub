<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateIndividualRateTable extends Migration
{
    public function up()
    {
        Schema::create('individual_rate', function (Blueprint $table) {
            $table->increments('id');
            $table->decimal('rate', 2, 1);
            $table->unsignedInteger('idUser');
            $table->unsignedInteger('idFilm');
            $table->timestamps();

            $table->foreign('idUser')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('idFilm')->references('idFilm')->on('films')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('individual_rate');
    }
}
;
