<?php

namespace App\Jobs;

use App\Models\NewsItem;
use App\Models\NewsSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Descarga y parsea un feed RSS/Atom, guardando los items nuevos en news_items.
 *
 * - Deduplicación por original_url (UNIQUE en BD, también comprobamos antes de insertar).
 * - Solo guarda raw_content; nunca lo expone directamente al público.
 * - Respeta el User-Agent identificativo.
 */
class FetchRssSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 3;
    public $backoff = 60;
    public $timeout = 60;

    public function __construct(public readonly int $sourceId) {}

    public function handle(): void
    {
        $source = NewsSource::find($this->sourceId);

        if (! $source || ! $source->is_active) {
            return;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'CinemaClub-Bot/1.0 (cinemaclub.es)',
            ])->timeout(30)->get($source->url);

            if (! $response->successful()) {
                Log::warning("[FetchRssSourceJob] #{$source->id} HTTP {$response->status()} para {$source->url}");
                return;
            }

            $body = $response->body();
            $saved = $this->parseAndSave($source, $body);

            $source->recordSuccess();
            Log::info("[FetchRssSourceJob] #{$source->id} ({$source->name}): {$saved} items nuevos.");

        } catch (\Throwable $e) {
            $source->recordFailure($e->getMessage());
            Log::error("[FetchRssSourceJob] #{$source->id} error: {$e->getMessage()}");
            throw $e;
        }
    }

    private function parseAndSave(NewsSource $source, string $xml): int
    {
        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($feed === false) {
            Log::warning("[FetchRssSourceJob] #{$source->id}: XML inválido.");
            return 0;
        }

        // Detectar si es RSS 2.0 o Atom
        $items = [];

        if (isset($feed->channel->item)) {
            // RSS 2.0
            foreach ($feed->channel->item as $item) {
                $items[] = [
                    'title'   => (string) $item->title,
                    'url'     => (string) ($item->link ?? $item->guid),
                    'content' => (string) ($item->children('content', true)->encoded ?? $item->description ?? ''),
                    'pubDate' => (string) ($item->pubDate ?? ''),
                ];
            }
        } elseif (isset($feed->entry)) {
            // Atom
            $ns = $feed->getNamespaces(true);
            foreach ($feed->entry as $entry) {
                $link = '';
                foreach ($entry->link as $l) {
                    $rel = (string) $l['rel'];
                    if ($rel === 'alternate' || $rel === '') {
                        $link = (string) $l['href'];
                        break;
                    }
                }
                $items[] = [
                    'title'   => (string) $entry->title,
                    'url'     => $link,
                    'content' => (string) ($entry->content ?? $entry->summary ?? ''),
                    'pubDate' => (string) ($entry->updated ?? $entry->published ?? ''),
                ];
            }
        }

        $saved = 0;

        foreach ($items as $item) {
            $url = trim($item['url']);
            if (empty($url) || empty($item['title'])) {
                continue;
            }

            // Deduplicación
            if (NewsItem::where('original_url', $url)->exists()) {
                continue;
            }

            try {
                NewsItem::create([
                    'source_id'   => $source->id,
                    'title'       => mb_substr(strip_tags($item['title']), 0, 255),
                    'original_url'=> $url,
                    'raw_content' => strip_tags($item['content']),
                    'status'      => 'pending',
                    'found_at'    => now(),
                ]);
                $saved++;
            } catch (\Illuminate\Database\QueryException $e) {
                // Carrera de duplicados: la constraint UNIQUE lo capturará
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                    continue;
                }
                throw $e;
            }
        }

        return $saved;
    }
}
