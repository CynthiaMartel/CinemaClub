<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
        $table->id();
        $table->unsignedInteger('user_id');
        $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        $table->string('avatar')->nullable();
        $table->text('bio')->nullable();
        $table->string('location')->nullable();
        $table->string('website')->nullable();
        $table->json('top_5_films')->nullable();
        $table->unsignedSmallInteger('films_seen')->default(0);
        $table->unsignedSmallInteger('films_rated')->default(0);
        $table->unsignedSmallInteger('films_seen_this_year')->default(0);
        $table->unsignedSmallInteger('lists_created')->default(0);
        $table->unsignedSmallInteger('lists_saved')->default(0);
        $table->unsignedSmallInteger('followers_count')->default(0);
        $table->unsignedSmallInteger('followings_count')->default(0);
        $table->timestamps();
    });


    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};

