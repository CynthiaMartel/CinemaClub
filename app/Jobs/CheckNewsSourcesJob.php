<?php

namespace App\Jobs;

use App\Models\NewsSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job maestro: itera las fuentes activas y vencidas para rastreo
 * y despacha el job específico según el tipo de cada fuente.
 *
 * El Scheduler lo ejecuta cada 2 horas. El respeto del check_interval_hours
 * de cada fuente se comprueba dentro de isDue().
 */
class CheckNewsSourcesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 1;
    public $timeout = 120;

    public function handle(): void
    {
        $sources = NewsSource::active()->get();
        $dispatched = 0;

        foreach ($sources as $source) {
            if (! $source->isDue()) {
                continue;
            }

            try {
                match ($source->type) {
                    'rss'      => FetchRssSourceJob::dispatch($source->id),
                    'scraping' => FetchScrapingSourceJob::dispatch($source->id),
                    'sitemap'  => FetchRssSourceJob::dispatch($source->id), // sitemap parsed igual que rss
                    default    => null,
                };
                $dispatched++;
            } catch (\Throwable $e) {
                Log::error("[CheckNewsSourcesJob] Error al despachar fuente #{$source->id} ({$source->name}): {$e->getMessage()}");
            }
        }

        Log::info("[CheckNewsSourcesJob] Despachados {$dispatched} jobs de rastreo.");
    }
}
