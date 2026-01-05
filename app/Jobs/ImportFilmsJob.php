<?php

/**
 * JOB: ImportFilmsJob
 * ----------------------------------------------_____
 * Este Job permite ejecutar la importación de películas en segundo plano de manera asíncrona.
 * En lugar de procesar MUCHAS películas directamente en una SOLA petición HTTP
 * (lo que bloquearía el servidor o daría errores), Laravel envía este trabajo (por eso job)
 * a la "cola de trabajos" (Queue) para que sea procesado de manera asíncrona
 * por un worker (`php artisan queue:work`).
 *
 * De esta forma, la aplicación puede seguir funcionando mientras
 * la importación se realiza en background sin afectar el rendimiento.
 * * En este contexto, el Job ejecuta el método importFromTMDB() del controlador
 * FilmDataController, manteniendo la lógica original del proceso.
 * * Además, el Queue tiene su propia migración para almacenar los trabajos pendientes, de llenado de 
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

    // Usamos estas propiedades nativas para que Laravel gestione los errores de forma automática
    public $tries = 3;         // Intentos totales si falla antes de marcarlo como "Failed"
    public $backoff = 30;      // Segundos a esperar entre reintentos automáticos
    public $timeout = 600;     // 10 minutos máximo para evitar que el proceso se corte por tiempo

    public int $yearStart;
    public int $yearEnd;
    public int $startPage;
    public int $endPage;

    /**
     * Crea una nueva instancia del Job con los parámetros necesarios para la API
     */
    public function __construct(int $yearStart, int $yearEnd, int $startPage = 1, int $endPage = 1)
    {
        $this->yearStart = $yearStart;
        $this->yearEnd   = $yearEnd;
        $this->startPage = $startPage;
        $this->endPage   = $endPage;
    }

    // Ejecuta la lógica del Job
     
    public function handle(): void
    {
        Log::info("Job ImportFilmsJob iniciado: Periodo {$this->yearStart}-{$this->yearEnd}, páginas {$this->startPage}-{$this->endPage}");

        
        // Llamamos directamente a la lógica principal. Si ocurre un error de conexión 
        // o de API, Laravel captura la excepción y reintenta el Job según $tries.
        app(FilmDataController::class)->importFromTMDB(
            $this->yearStart,
            $this->yearEnd,
            $this->startPage,
            $this->endPage
        );

        Log::info("Job ImportFilmsJob finalizado con éxito.");
    }

    /**
    
     * Este método se dispara automáticamente cuando se han fallado los 3 intentos ($tries)
     */
    public function failed(\Throwable $exception)
    {
        Log::error("El Job de importación falló definitivamente tras {$this->tries} intentos. Error: " . $exception->getMessage());
    }
}