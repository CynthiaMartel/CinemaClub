<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('password');
            $table->string('email', 50)->unique();
            $table->unsignedInteger('idRol');
            $table->string('ipLastAccess', 20)->nullable()->default('');
            $table->dateTime('dateHourLastAccess')->nullable();
            $table->smallInteger('failedAttempts')->default(0);
            $table->boolean('blocked')->default(false);
            $table->rememberToken(); // helper de Laravel que usa internamente pero al usar Sanctum, no es tan necesario
            $table->timestamps();

            $table->foreign('idRol')->references('id')->on('rol')->onDelete('restrict')->onUpdate('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
    }
}

