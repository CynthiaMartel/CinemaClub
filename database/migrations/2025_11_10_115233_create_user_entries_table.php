<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_entries', function (Blueprint $table) {
            $table->increments('id'); // INT UNSIGNED AUTO_INCREMENT
            
            $table->unsignedInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->enum('type', ['user_list', 'user_debate', 'user_review'])->default('user_list');
            $table->string('title', 255);
            $table->text('content')->nullable();
            $table->enum('visibility', ['public', 'friends', 'private'])->default('public');
            $table->boolean('allow_comments')->default(true);
            $table->string('cover_image')->nullable();
            $table->unsignedInteger('likes_count')->default(0);
            $table->enum('status', ['approved', 'pending_review', 'blocked'])->default('approved');
            $table->text('moderation_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_entries');
    }
};



