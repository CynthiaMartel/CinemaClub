<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_saved_lists', function (Blueprint $table) {
            $table->increments('id'); // igual que en users y user_entries

            // Para que usuario guarde la lista
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Para ID de la lista guardada (entrada tipo user_list)
            $table->unsignedInteger('user_entry_id');
            $table->foreign('user_entry_id')->references('id')->on('user_entries')->onDelete('cascade');

            $table->timestamps();

            $table->unique(['user_id', 'user_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_saved_lists');
    }
};



