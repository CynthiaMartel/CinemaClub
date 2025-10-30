<?php
/**
 * QUEUE (Cola de trabajos) y migración de tabla "jobs"
 * --------------------------------------------------------------
 * Laravel usa un sistema de colas (Queue) para almacenar trabajos pendientes.
 * Cuando se lanza un Job, este se guarda como un registro en la tabla "jobs".
 * 
 * Esa tabla se genera mediante el comando:
 *  php artisan queue:table
 * y se aplica con:
 *  php artisan migrate
 * 
 * Luego, un proceso "worker" ejecuta los trabajos en orden:
 *   php artisan queue:work
 * 
 * En este contexto, la cola permite importar películas en lotes
 * sin bloquear el servidor, distribuyendo el trabajo de forma segura
 * y controlada en segundo plano y asíncrona.
 */

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
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
