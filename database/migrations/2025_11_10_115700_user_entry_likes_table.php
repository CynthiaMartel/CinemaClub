<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_entry_likes', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_entry_id');
            $table->unsignedInteger('user_id');
            $table->timestamps();

            $table->foreign('user_entry_id')->references('id')->on('user_entries')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['user_entry_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_entry_likes');
    }
};



