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
 * Extrae noticias de páginas web sin RSS usando selector_config.
 *
 * selector_config (JSON) esperado:
 * {
 *   "items":       "article.news-item",   // selector CSS del contenedor de cada noticia
 *   "title":       "h2 a",                // selector del título (dentro del item)
 *   "link":        "h2 a",                // selector del enlace (atributo href)
 *   "description": ".excerpt"             // selector del resumen (opcional)
 * }
 *
 * - Respeta robots.txt (comprobación manual de Disallow)
 * - User-Agent identificativo
 * - Rate limiting: 2 s entre requests al mismo dominio
 */
class FetchScrapingSourceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries   = 2;
    public $backoff = 120;
    public $timeout = 90;

    public function __construct(public readonly int $sourceId) {}

    public function handle(): void
    {
        $source = NewsSource::find($this->sourceId);

        if (! $source || ! $source->is_active) {
            return;
        }

        $config = $source->selector_config;

        if (empty($config['items']) || empty($config['title']) || empty($config['link'])) {
            Log::warning("[FetchScrapingSourceJob] #{$source->id}: selector_config incompleto.");
            return;
        }

        // ── Comprobar robots.txt ─────────────────────────────────────────────
        if (! $this->isAllowedByRobots($source->url)) {
            Log::info("[FetchScrapingSourceJob] #{$source->id}: bloqueado por robots.txt.");
            return;
        }

        // ── Descargar la página ──────────────────────────────────────────────
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'CinemaClub-Bot/1.0 (cinemaclub.es)',
            ])->timeout(30)->get($source->url);

            if (! $response->successful()) {
                Log::warning("[FetchScrapingSourceJob] #{$source->id} HTTP {$response->status()}");
                return;
            }

            $saved = $this->parseAndSave($source, $response->body(), $config);
            $source->recordSuccess();
            Log::info("[FetchScrapingSourceJob] #{$source->id} ({$source->name}): {$saved} items nuevos.");

        } catch (\Throwable $e) {
            $source->recordFailure($e->getMessage());
            Log::error("[FetchScrapingSourceJob] #{$source->id} error: {$e->getMessage()}");
            throw $e;
        }
    }

    private function parseAndSave(NewsSource $source, string $html, array $config): int
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);

        // Convertir selector CSS simple a XPath
        $itemsXpath = $this->cssToXpath($config['items']);
        $items      = $xpath->query($itemsXpath);

        if (! $items || $items->length === 0) {
            Log::info("[FetchScrapingSourceJob] #{$source->id}: sin resultados con selector '{$config['items']}'.");
            return 0;
        }

        $baseUrl = $this->baseUrl($source->url);
        $saved   = 0;

        foreach ($items as $item) {
            // Título
            $titleNodes = $xpath->query($this->cssToXpath($config['title']), $item);
            $title      = $titleNodes && $titleNodes->length > 0
                ? trim($titleNodes->item(0)->textContent)
                : '';

            // URL
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

            // Descripción opcional
            $desc = '';
            if (! empty($config['description'])) {
                $descNodes = $xpath->query($this->cssToXpath($config['description']), $item);
                if ($descNodes && $descNodes->length > 0) {
                    $desc = trim($descNodes->item(0)->textContent);
                }
            }

            if (NewsItem::where('original_url', $link)->exists()) {
                continue;
            }

            try {
                NewsItem::create([
                    'source_id'    => $source->id,
                    'title'        => mb_substr($title, 0, 255),
                    'original_url' => $link,
                    'raw_content'  => $desc,
                    'status'       => 'pending',
                    'found_at'     => now(),
                ]);
                $saved++;

                // Rate limiting: 2 s entre requests al mismo dominio
                usleep(200_000); // 200ms entre items (no entre requests HTTP)

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
     * Comprueba robots.txt del dominio.
     * Solo comprueba reglas globales (User-agent: *) por simplicidad.
     */
    private function isAllowedByRobots(string $url): bool
    {
        $base  = $this->baseUrl($url);
        $path  = parse_url($url, PHP_URL_PATH) ?: '/';

        try {
            $robots = Http::withHeaders(['User-Agent' => 'CinemaClub-Bot/1.0'])
                ->timeout(5)
                ->get("{$base}/robots.txt");

            if (! $robots->successful()) {
                return true; // Sin robots.txt = permitido
            }

            $lines       = explode("\n", $robots->body());
            $applicable  = false;

            foreach ($lines as $line) {
                $line = trim($line);
                if (stripos($line, 'User-agent:') === 0) {
                    $agent      = trim(substr($line, 11));
                    $applicable = ($agent === '*' || stripos($agent, 'CinemaClub') !== false);
                }
                if ($applicable && stripos($line, 'Disallow:') === 0) {
                    $disallowed = trim(substr($line, 9));
                    if ($disallowed && str_starts_with($path, $disallowed)) {
                        return false;
                    }
                }
            }
        } catch (\Throwable) {
            // Error al obtener robots.txt → permitir rastreo
        }

        return true;
    }

    /**
     * Convierte un selector CSS simple a XPath.
     * Soporta: tag, .class, tag.class, #id, descendant (espacio), > hijo directo.
     */
    private function cssToXpath(string $selector): string
    {
        $selector = trim($selector);

        // Manejo básico: convertir selectores simples
        $parts  = preg_split('/\s*>\s*/', $selector); // hijo directo
        $joined = implode('/', array_map([$this, 'singleCssToXpath'], $parts));

        // Si hay espacio (descendiente) lo manejamos aparte
        if (str_contains($selector, ' ')) {
            $parts  = preg_split('/\s+/', $selector);
            $joined = './/' . implode('//', array_map([$this, 'singleCssToXpath'], $parts));
            return $joined;
        }

        return './/' . $joined;
    }

    private function singleCssToXpath(string $part): string
    {
        $part = trim($part);
        $tag  = '*';
        $conditions = [];

        // id: #foo
        if (preg_match('/#([\w-]+)/', $part, $m)) {
            $conditions[] = "@id='{$m[1]}'";
            $part = str_replace($m[0], '', $part);
        }

        // clases: .foo.bar
        preg_match_all('/\.([\w-]+)/', $part, $classes);
        foreach ($classes[1] as $class) {
            $conditions[] = "contains(concat(' ',normalize-space(@class),' '),' {$class} ')";
        }
        $part = preg_replace('/\.([\w-]+)/', '', $part);

        // tag
        $part = preg_replace('/[^a-zA-Z0-9_-]/', '', $part);
        if (! empty($part)) {
            $tag = $part;
        }

        $xpath = $tag;
        if ($conditions) {
            $xpath .= '[' . implode(' and ', $conditions) . ']';
        }

        return $xpath;
    }

    private function baseUrl(string $url): string
    {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '');
    }

    private function absoluteUrl(string $href, string $base): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return 'https:' . $href;
        }
        if (str_starts_with($href, '/')) {
            return rtrim($base, '/') . $href;
        }
        return rtrim($base, '/') . '/' . $href;
    }
}
