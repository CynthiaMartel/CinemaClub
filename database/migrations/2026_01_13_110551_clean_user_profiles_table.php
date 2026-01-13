<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up()
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Borramos las columnas de conteo que ya no usaremos, al darme cuenta de que es mejor normalizarlas!
            $table->dropColumn([
                'films_seen',
                'films_rated',
                'films_seen_this_year',
                'lists_created',
                'lists_saved',
                'followers_count',
                'followings_count'
            ]);
        });
    }

    public function down()
    {
        Schema::table('user_profiles', function (Blueprint $table) {
            // Por si queremos volver a atrás a la antigua tabñla
            $table->integer('films_seen')->default(0);
            $table->integer('films_rated')->default(0);
            $table->integer('films_seen_this_year')->default(0);
            $table->integer('lists_created')->default(0);
            $table->integer('lists_saved')->default(0);
            $table->integer('followers_count')->default(0);
            $table->integer('followings_count')->default(0);
        });
    }
};
