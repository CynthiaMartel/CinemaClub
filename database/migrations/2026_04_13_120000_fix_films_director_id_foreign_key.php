<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // La FK apuntaba a cast_crew_backup en lugar de cast_crew.
        // Este fix la elimina y la recrea correctamente.
        Schema::table('films', function (Blueprint $table) {
            $table->dropForeign('films_director_id_foreign');
            $table->foreign('director_id')
                  ->references('idPerson')
                  ->on('cast_crew')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // The original broken state pointed director_id at cast_crew_backup,
        // which no longer exists. Rolling back to that state is not safe or
        // meaningful, so we leave the correct FK in place.
        // If you need to fully revert, drop the FK manually.
    }
};
