<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\ImportFilmsJob;
use App\Jobs\CheckNewsSourcesJob;
use App\Jobs\ProcessNewsItemWithAIJob;
use App\Jobs\FetchEventSourceJob;
use App\Jobs\ProcessEventWithAIJob;
use App\Models\NewsItem;
use App\Models\CinemaEvent;
use App\Models\NewsSource;

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

// ── Panel Editorial IA ─────────────────────────────────────────────────────

// Rastreo de fuentes de noticias: cada 2 horas
// El job respeta check_interval_hours de cada fuente individualmente
Schedule::job(new CheckNewsSourcesJob())
    ->everyTwoHours()
    ->name('check-news-sources')
    ->withoutOverlapping();

// Procesamiento IA de items pendientes: cada hora
// Solo procesa items sin ai_summary en lotes de 20
Schedule::job(new ProcessNewsItemWithAIJob())
    ->hourly()
    ->name('process-news-ai')
    ->withoutOverlapping();

// ── Event Manager ──────────────────────────────────────────────────────────

// Rastreo de fuentes de eventos (purpose='events'): cada 6 horas
// Lanza un job por cada fuente de eventos activa
Schedule::call(function () {
    NewsSource::active()
        ->where('purpose', 'events')
        ->each(fn ($source) => FetchEventSourceJob::dispatch($source->id));
})
    ->everySixHours()
    ->name('fetch-event-sources')
    ->withoutOverlapping();

// Procesamiento IA de eventos pendientes: cada hora
// Solo procesa eventos sin ai_confidence en lotes de 15
Schedule::job(new ProcessEventWithAIJob())
    ->hourly()
    ->name('process-events-ai')
    ->withoutOverlapping();

// ── Limpieza periódica ─────────────────────────────────────────────────────

// Eliminar NewsItems que llevan más de 1 mes sin convertirse en post.
// Se mantienen los 'drafted' (ya tienen Post asociado) indefinidamente.
Schedule::call(function () {
    $deleted = NewsItem::whereIn('status', ['pending', 'approved', 'rejected'])
        ->where('found_at', '<', now()->subMonth())
        ->delete();
    \Illuminate\Support\Facades\Log::info("[Pruning] NewsItems eliminados: {$deleted}");
})
    ->monthly()
    ->name('prune-news-items')
    ->description('Elimina noticias pendientes/rechazadas con más de 1 mes de antigüedad');

// Eliminar CinemaEvents finalizados hace más de 2 meses.
// Cubre festivales largos: end_date (o start_date si no hay end_date) < hoy - 2 meses.
Schedule::call(function () {
    $cutoff  = now()->subMonths(2)->toDateString();
    $deleted = CinemaEvent::where(function ($q) use ($cutoff) {
        $q->whereNotNull('end_date')->where('end_date', '<', $cutoff);
    })->orWhere(function ($q) use ($cutoff) {
        $q->whereNull('end_date')->where('start_date', '<', $cutoff);
    })->delete();
    \Illuminate\Support\Facades\Log::info("[Pruning] CinemaEvents eliminados: {$deleted}");
})
    ->monthly()
    ->name('prune-cinema-events')
    ->description('Elimina eventos de cine finalizados hace más de 2 meses');
