<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePostTable extends Migration
{
    public function up()
    {
        Schema::create('post', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('idUser');
            $table->text('title');
            $table->text('subtitle');
            $table->text('content');
            $table->string('img', 255)->nullable();
            $table->boolean('visible')->default(true);
            $table->text('editorName');
            $table->timestamps();

            $table->foreign('idUser')->references('id')->on('users')->onDelete('cascade')->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('post');
    }
}
