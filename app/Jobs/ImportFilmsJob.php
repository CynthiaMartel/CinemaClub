<?php
/**
 * JOB: ImportFilmsJob
 * ----------------------------------------------_____
 * Este Job permite ejecutar la importación de películas en segundo plano de manera asíncrona.
 * En lugar de procesar MUCHAS películas directamente en una SOLA petición HTTP
 * (lo que bloquearía el servidor o daría errores), Laravel envía este trabajo(por eso job)
 * a la "cola de trabajos" (Queue) para que sea procesado de manera asíncrona
 * por un worker (`php artisan queue:work`).
 *
 * De esta forma, la aplicación puede seguir funcionando mientras
 * la importación se realiza en background sin afectar el rendimiento.
 * 
 * En este contexto, el Job ejecuta el método importFromTMDB() del controlador
 * FilmDataController, manteniendo la lógica original del proceso.
 * 
 * Además, el Queque tiene su propia migración para almacenar los trabajos pendientes, de llenado de 
 * datos de la BD.
 */

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\FilmDataController;

class ImportFilmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $yearStart;
    public int $yearEnd;
    public int $pages;

    public function __construct(int $yearStart, int $yearEnd, int $pages = 1)
    {
        $this->yearStart = $yearStart;
        $this->yearEnd   = $yearEnd;
        $this->pages     = $pages;
    }

    public function handle(): void
    {
        // Delegamos en tu propio controlador para NO tocar la lógica
        Log::info("🧵 Job ImportFilmsJob iniciado: {$this->yearStart}-{$this->yearEnd}, pages={$this->pages}");
        app(FilmDataController::class)->importFromTMDB($this->yearStart, $this->yearEnd, $this->pages);
        Log::info("🧵 Job ImportFilmsJob finalizado");
    }
}


