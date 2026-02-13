<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            //Agregamos los contadores. Usamos default(0) para que empiecen en cero
            //   'after'para ordenar visualmente la tabla.
            $table->unsignedInteger('followers_count')->default(0)->after('user_id');
            $table->unsignedInteger('following_count')->default(0)->after('followers_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            $table->dropColumn(['followers_count', 'following_count']);
        });
    }
};