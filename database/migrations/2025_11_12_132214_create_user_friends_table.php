<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_friends', function (Blueprint $table) {
            $table->id();
            
            // Usuario que sigue
            $table->unsignedInteger('follower_id');
            $table->foreign('follower_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Usuario seguido
            $table->unsignedInteger('followed_id');
            $table->foreign('followed_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Estado de la relación (follow o bloqueado)
            $table->enum('status', ['accepted', 'blocked'])->default('accepted');

            $table->timestamps();

            // Evita duplicados (una relación única entre dos usuarios)
            $table->unique(['follower_id', 'followed_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_friends');
    }
};
