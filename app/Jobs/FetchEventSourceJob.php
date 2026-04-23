<?php

namespace App\Jobs;

use App\Models\CinemaEvent;
use App\Models\NewsSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extrae eventos de cine desde fuentes con purpose='events'.
 *
 * Reutiliza la misma lógica de scraping CSS + fallback OG/alt que
 * FetchScrapingSourceJob, pero guarda en cinema_events en lugar de news_items.
 *
 * selector_config esperado (campos adicionales para eventos):
 * {
 *   "items":       ".event-card",      // contenedor de cada evento
 *   "title":       "h3",
 *   "link":        "a.event-link",
 *   "date":        ".event-date",      // texto de fecha (procesado por IA)
 *   "venue":       ".event-venue",     // opcional
 *   "description": ".event-body",      // opcional
 *   "image":       "img.event-poster"  // opcional, extrae src
 * }
 *
 * El raw_text que llega al ProcessEventWithAIJob incluye:
 *   - Texto de los selectores configurados
 *   - Fallback: alt de imágenes + OpenGraph si no hay texto suficiente
 */
class FetchEventSourceJob extends FetchScrapingSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 2;
    public $backoff = 120;
    public $timeout = 90;

    public function __construct(public readonly int $sourceId) {}

    public function handle(): void
    {
        $source = NewsSource::find($this->sourceId);

        if (! $source || ! $source->is_active || $source->purpose !== 'events') {
            return;
        }

        // ── RSS: parser dedicado, sin selectores CSS ──────────────────────
        if ($source->type === 'rss') {
            $saved = $this->handleRss($source);
            Log::info("[FetchEventSourceJob RSS] #{$source->id} ({$source->name}): {$saved} eventos nuevos.");
            return;
        }

        // ── Scraping HTML ─────────────────────────────────────────────────
        $config = $source->selector_config;

        if (empty($config['items']) || empty($config['title']) || empty($config['link'])) {
            Log::warning("[FetchEventSourceJob] #{$source->id}: selector_config incompleto.");
            return;
        }

        if (! $this->isAllowedByRobots($source->url)) {
            Log::info("[FetchEventSourceJob] #{$source->id}: bloqueado por robots.txt.");
            return;
        }

        try {
            $response = $this->fetchUrl($source->url);

            if (! $response || ! $response->successful()) {
                Log::warning("[FetchEventSourceJob] #{$source->id} HTTP " . ($response?->status() ?? 'null'));
                return;
            }

            $html  = $response->body();
            $saved = $this->parseAndSaveEvents($source, $html, $config);
            $source->recordSuccess();
            Log::info("[FetchEventSourceJob] #{$source->id} ({$source->name}): {$saved} eventos nuevos.");

        } catch (\Throwable $e) {
            $source->recordFailure($e->getMessage());
            Log::error("[FetchEventSourceJob] #{$source->id} error: {$e->getMessage()}");
            throw $e;
        }
    }

    /**
     * Parsea un feed RSS/Atom y guarda ítems como CinemaEvents pendientes.
     * Soporta RSS 2.0 estándar y WordPress (content:encoded).
     */
    private function handleRss(NewsSource $source): int
    {
        $response = $this->fetchUrl($source->url);

        if (! $response || ! $response->successful()) {
            $source->recordFailure('HTTP error al descargar RSS');
            return 0;
        }

        libxml_use_internal_errors(true);
        $feed = simplexml_load_string($response->body());

        if (! $feed) {
            $source->recordFailure('XML inválido o feed RSS no parseado');
            return 0;
        }

        // Soporte RSS 2.0 (<channel><item>) y Atom (<feed><entry>)
        $items = $feed->channel->item ?? $feed->entry ?? [];
        $saved = 0;

        foreach ($items as $item) {
            $title = trim((string) ($item->title ?? ''));
            $link  = trim((string) ($item->link ?? $item->guid ?? ''));

            // Atom: <link href="..."> en lugar de texto
            if (empty($link) && isset($item->link['href'])) {
                $link = trim((string) $item->link['href']);
            }

            if (empty($title) || empty($link)) {
                continue;
            }

            // Contenido: WordPress content:encoded → description → summary (Atom)
            $namespaces = $item->getNamespaces(true);
            $rawContent = '';

            if (isset($namespaces['content'])) {
                $contentNs  = $item->children($namespaces['content']);
                $rawContent = (string) ($contentNs->encoded ?? '');
            }

            if (empty($rawContent)) {
                $rawContent = (string) ($item->description ?? $item->summary ?? '');
            }

            $plainText = trim(preg_replace('/\s+/', ' ', strip_tags($rawContent)));

            if (strlen($plainText) < 20) {
                continue;
            }

            // Deduplicación por URL fuente
            if (CinemaEvent::where('source_url', $link)->exists()) {
                continue;
            }

            $rawText = $title . "\n" . $plainText;

            try {
                CinemaEvent::create([
                    'source_id'  => $source->id,
                    'title'      => mb_substr($title, 0, 255),
                    'source_url' => $link,
                    'raw_text'   => mb_substr($rawText, 0, 3000),
                    'status'     => 'pending',
                    'start_date' => now()->toDateString(), // placeholder; la IA corregirá
                ]);
                $saved++;
                usleep(100_000); // 100 ms entre ítems
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                    continue;
                }
                throw $e;
            }
        }

        $source->recordSuccess();
        return $saved;
    }

    private function parseAndSaveEvents(NewsSource $source, string $html, array $config): int
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        $itemsXpath = $this->cssToXpath($config['items']);
        $items      = $xpath->query($itemsXpath);

        if (! $items || $items->length === 0) {
            Log::info("[FetchEventSourceJob] #{$source->id}: sin resultados con selector '{$config['items']}'.");
            return 0;
        }

        $baseUrl = $this->baseUrl($source->url);
        $og      = $this->extractOpenGraph($html); // fallback de página completa
        $saved   = 0;

        foreach ($items as $item) {
            // ── Título ───────────────────────────────────────────────────────
            $titleNodes = $xpath->query($this->cssToXpath($config['title']), $item);
            $title      = $titleNodes && $titleNodes->length > 0
                ? trim($titleNodes->item(0)->textContent)
                : '';

            // ── URL ──────────────────────────────────────────────────────────
            $linkNodes = $xpath->query($this->cssToXpath($config['link']), $item);
            $link      = '';
            if ($linkNodes && $linkNodes->length > 0) {
                $linkNode = $linkNodes->item(0);
                $href     = $linkNode->getAttribute('href') ?: trim($linkNode->textContent);
                $link     = $this->absoluteUrl($href, $baseUrl);
            }

            if (empty($title) || empty($link)) {
                continue;
            }

            // ── Fecha (texto bruto para la IA) ───────────────────────────────
            $dateText = '';
            if (! empty($config['date'])) {
                $dateNodes = $xpath->query($this->cssToXpath($config['date']), $item);
                if ($dateNodes && $dateNodes->length > 0) {
                    $dateText = trim($dateNodes->item(0)->textContent);
                }
            }

            // ── Venue ────────────────────────────────────────────────────────
            $venue = '';
            if (! empty($config['venue'])) {
                $venueNodes = $xpath->query($this->cssToXpath($config['venue']), $item);
                if ($venueNodes && $venueNodes->length > 0) {
                    $venue = trim($venueNodes->item(0)->textContent);
                }
            }

            // ── Descripción ──────────────────────────────────────────────────
            $desc = '';
            if (! empty($config['description'])) {
                $descNodes = $xpath->query($this->cssToXpath($config['description']), $item);
                if ($descNodes && $descNodes->length > 0) {
                    $desc = trim($descNodes->item(0)->textContent);
                }
            }

            // ── Imagen (src del cartel) ──────────────────────────────────────
            $imageUrl = null;
            if (! empty($config['image'])) {
                $imgNodes = $xpath->query($this->cssToXpath($config['image']), $item);
                if ($imgNodes && $imgNodes->length > 0) {
                    $src = $imgNodes->item(0)->getAttribute('src');
                    if ($src) {
                        $imageUrl = $this->absoluteUrl($src, $baseUrl);
                    }
                }
            }

            // ── Fallback alt de imágenes si no hay descripción ni fecha ──────
            if (empty($desc) && empty($dateText)) {
                $imgNodes = $xpath->query('.//img[@alt]', $item);
                $alts = [];
                foreach ($imgNodes as $img) {
                    $alt = trim($img->getAttribute('alt'));
                    if (strlen($alt) > 10) {
                        $alts[] = $alt;
                    }
                }
                if (! empty($alts)) {
                    $desc = implode(' | ', $alts);
                }
            }

            // ── Construir raw_text para la IA ────────────────────────────────
            // Si hay muy poco texto, incluir también el fallback OpenGraph de la página
            $rawParts = array_filter([
                $title,
                $dateText ? "Fecha: {$dateText}" : null,
                $venue    ? "Lugar: {$venue}"    : null,
                $desc,
            ]);
            $rawText = implode("\n", $rawParts);

            if (strlen($rawText) < 30 && $og['og_description']) {
                $rawText .= "\n[OG] " . $og['og_description'];
            }

            // ── Deduplicación por URL ────────────────────────────────────────
            if (CinemaEvent::where('source_url', $link)->exists()) {
                continue;
            }

            try {
                CinemaEvent::create([
                    'source_id'  => $source->id,
                    'title'      => mb_substr($title, 0, 255),
                    'source_url' => $link,
                    'image_url'  => $imageUrl,
                    'raw_text'   => mb_substr($rawText, 0, 3000),
                    'status'     => 'pending',
                    // start_date, end_date, venue, island, event_type → los rellena la IA
                    'start_date' => now()->toDateString(), // placeholder; la IA lo corregirá
                ]);
                $saved++;

                usleep(200_000); // 200ms entre items

            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'UNIQUE')) {
                    continue;
                }
                throw $e;
            }
        }

        return $saved;
    }

    /**
     * Descarga una URL con fallback: primero con SSL estricto, luego sin verificar.
     * Para scraping de contenido público es aceptable ignorar certs caducados.
     */
    protected function fetchUrl(string $url): ?\Illuminate\Http\Client\Response
    {
        $headers = ['User-Agent' => 'CinemaClub-Bot/1.0 (cinemaclub.es)'];

        try {
            $response = Http::withHeaders($headers)->timeout(30)->get($url);
            if ($response->successful()) {
                return $response;
            }
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // SSL caducado u otro error de conexión → reintentar sin verificar cert
            if (str_contains($e->getMessage(), 'SSL') || str_contains($e->getMessage(), 'certificate') || str_contains($e->getMessage(), 'TLS')) {
                Log::info("[FetchEventSourceJob] SSL error en {$url}, reintentando sin verificación.");
                try {
                    return Http::withHeaders($headers)
                        ->withOptions(['verify' => false])
                        ->timeout(30)
                        ->get($url);
                } catch (\Throwable) {
                    return null;
                }
            }
            throw $e;
        }

        return null;
    }

    // Los métodos cssToXpath, singleCssToXpath, isAllowedByRobots,
    // extractOpenGraph, baseUrl y absoluteUrl se heredan de FetchScrapingSourceJob.
}
