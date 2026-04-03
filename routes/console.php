<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ImportFilmsJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Importación semanal automática de películas desde TMDB
// Cada lunes a las 03:00 AM trae las películas del año en curso y el anterior (páginas 1-3)
Schedule::job(new ImportFilmsJob(
    now()->subYear()->year,  // yearStart: año anterior
    now()->year,             // yearEnd:   año en curso
    1,                       // startPage
    3                        // endPage
))->weeklyOn(1, '10:00')    // Lunes a las 10:00 AM
  ->name('import-films-weekly')
  ->withoutOverlapping();   // Evita que se solapen si el job anterior aún no terminó
