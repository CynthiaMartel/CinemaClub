<?php

namespace App\Http\Controllers;

use App\Jobs\CheckNewsSourcesJob;
use App\Jobs\FetchRssSourceJob;
use App\Jobs\FetchScrapingSourceJob;
use App\Models\NewsItem;
use App\Models\NewsSource;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Panel editorial de IA — acceso restringido a Admin (1) y Editor (2).
 */
class EditorialController extends Controller
{
    // ─── Middleware helper ────────────────────────────────────────────────────

    private function authorizeEditor(): ?\Illuminate\Http\JsonResponse
    {
        $user = auth('sanctum')->user();
        if (! $user || ! in_array($user->idRol, [1, 2])) {
            return response()->json(['message' => 'Sin permisos.'], 403);
        }
        return null;
    }

    // ─── NEWS ITEMS ───────────────────────────────────────────────────────────

    /**
     * GET /api/editorial/news-items
     * Parámetros: status, source_id, category, min_score, date_from, date_to, page
     */
    public function index(Request $request): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $query = NewsItem::with('source');

        match ($request->input('sort', 'fecha_desc')) {
            'fecha_asc'       => $query->orderBy('found_at', 'asc'),
            'relevancia_desc' => $query->orderByRaw('ai_relevance_score IS NULL, ai_relevance_score DESC'),
            'relevancia_asc'  => $query->orderByRaw('ai_relevance_score IS NULL, ai_relevance_score ASC'),
            default           => $query->orderBy('found_at', 'desc'),
        };

        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }
        if ($request->filled('source_id')) {
            $query->where('source_id', $request->source_id);
        }
        if ($request->filled('category')) {
            $query->where('ai_category', $request->category);
        }
        if ($request->filled('min_score')) {
            $query->where('ai_relevance_score', '>=', (int) $request->min_score);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('found_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('found_at', '<=', $request->date_to);
        }

        $items = $query->paginate(20);

        return response()->json([
            'success' => 1,
            'data'    => $items,
        ]);
    }

    /**
     * GET /api/editorial/news-items/{id}
     */
    public function show(int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $item = NewsItem::with('source')->findOrFail($id);

        return response()->json([
            'success' => 1,
            'data'    => $item,
        ]);
    }

    /**
     * PATCH /api/editorial/news-items/{id}/status
     * Body: { "status": "approved|rejected|pending" }
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,drafted',
        ]);

        $item = NewsItem::findOrFail($id);
        $item->update(['status' => $validated['status']]);

        return response()->json([
            'success' => 1,
            'message' => 'Estado actualizado.',
            'data'    => $item,
        ]);
    }

    /**
     * POST /api/editorial/news-items/{id}/create-draft
     *
     * Crea (o actualiza si ya existe) el Post asociado a este news item.
     * Body: { "title", "summary", "content" (opcional, HTML completo del editor) }
     *
     * - Si se envía `content` (HTML del CKEditor), se usa directamente.
     * - Si solo viene `summary`, se construye el contenido a partir de él.
     */
    public function createDraft(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $user = auth('sanctum')->user();
        $item = NewsItem::with('source')->findOrFail($id);

        $validated = $request->validate([
            'title'   => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'content' => 'nullable|string',  // HTML completo del editor
            'visible' => 'nullable|boolean',
        ]);

        $title      = $validated['title']   ?? $item->ai_suggested_title ?? $item->title;
        $summary    = $validated['summary'] ?? $item->ai_summary         ?? '';
        $sourceName = $item->source?->name  ?? 'fuente externa';
        $tags       = is_array($item->ai_tags) ? implode(', ', $item->ai_tags) : '';

        // Si viene content completo del editor, lo usamos. Si no, construimos desde resumen.
        $content = ! empty($validated['content'])
            ? $validated['content']
            : $this->buildPostContent($summary, $item->original_url, $sourceName);

        $postData = [
            'idUser'     => $user->id,
            'title'      => $title,
            'subtitle'   => $tags,
            'content'    => $content,
            'visible'    => $validated['visible'] ?? 0,
            'editorName' => $user->name,
        ];

        // Si ya existía un borrador para este item, lo actualizamos en vez de crear uno nuevo
        if ($item->published_post_id && $post = Post::find($item->published_post_id)) {
            $post->update($postData);
        } else {
            $post = Post::create($postData);
        }

        $item->update([
            'status'            => 'drafted',
            'published_post_id' => $post->id,
        ]);

        return response()->json([
            'success' => 1,
            'message' => 'Borrador guardado correctamente.',
            'post_id' => $post->id,
            'data'    => $post,
        ], 201);
    }

    /**
     * POST /api/editorial/news-items/process-ai
     * Ejecuta el procesamiento IA de forma SÍNCRONA sobre los items pendientes sin procesar.
     * Útil en local cuando no hay queue worker corriendo.
     */
    public function processAI(): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        set_time_limit(180);

        $job = new \App\Jobs\ProcessNewsItemWithAIJob();
        $job->handle();

        $pending = \App\Models\NewsItem::unprocessed()->pending()->count();

        return response()->json([
            'success' => 1,
            'message' => $pending > 0
                ? "Lote procesado. Quedan {$pending} items sin procesar (pulsa de nuevo para continuar)."
                : 'Todos los items han sido procesados por la IA.',
            'pending_left' => $pending,
        ]);
    }

    // ─── SOURCES ──────────────────────────────────────────────────────────────

    /**
     * GET /api/editorial/sources
     */
    public function sources(): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $sources = NewsSource::orderBy('name')
            ->get()
            ->map(function (NewsSource $s) {
                return [
                    'id'                  => $s->id,
                    'name'                => $s->name,
                    'url'                 => $s->url,
                    'type'                => $s->type,
                    'is_active'           => $s->is_active,
                    'check_interval_hours'=> $s->check_interval_hours,
                    'last_checked_at'     => $s->last_checked_at?->toIso8601String(),
                    'items_today'         => $s->itemsFoundToday(),
                    'total_items'         => $s->newsItems()->count(),
                    'needs_review'        => (bool) $s->needs_review,
                    'failed_attempts'     => $s->failed_attempts ?? 0,
                    'last_error'          => $s->last_error,
                ];
            });

        return response()->json([
            'success' => 1,
            'data'    => $sources,
        ]);
    }

    /**
     * POST /api/editorial/sources/{id}/check-now
     * Ejecuta el rastreo de una fuente de forma SÍNCRONA (sin queue).
     * El resultado es inmediato — no requiere queue:work.
     */
    public function checkNow(int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        set_time_limit(60);

        $source = NewsSource::findOrFail($id);
        $before = $source->newsItems()->count();

        try {
            match ($source->type) {
                'rss', 'sitemap' => (new FetchRssSourceJob($source->id))->handle(),
                'scraping'       => (new FetchScrapingSourceJob($source->id))->handle(),
            };

            $new = max(0, $source->fresh()->newsItems()->count() - $before);
            $msg = $new > 0
                ? "{$new} nueva(s) noticia(s) encontradas en \"{$source->name}\"."
                : "\"{$source->name}\" rastreada — sin noticias nuevas por ahora.";

        } catch (\Throwable $e) {
            Log::warning("[checkNow] {$source->name}: {$e->getMessage()}");
            $msg = "Error al rastrear \"{$source->name}\": " . $e->getMessage();
        }

        return response()->json(['success' => 1, 'message' => $msg]);
    }

    /**
     * POST /api/editorial/sources/check-all
     * Ejecuta el rastreo de TODAS las fuentes activas de forma SÍNCRONA (sin queue).
     * Los resultados están disponibles inmediatamente en el inbox.
     */
    public function checkAll(): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        set_time_limit(120);

        $sources  = NewsSource::active()->get();
        $totalNew = 0;
        $results  = [];

        foreach ($sources as $source) {
            try {
                $before = $source->newsItems()->count();

                match ($source->type) {
                    'rss', 'sitemap' => (new FetchRssSourceJob($source->id))->handle(),
                    'scraping'       => (new FetchScrapingSourceJob($source->id))->handle(),
                };

                $new       = max(0, $source->fresh()->newsItems()->count() - $before);
                $totalNew += $new;
                $results[] = ['name' => $source->name, 'new' => $new, 'ok' => true];

            } catch (\Throwable $e) {
                $results[] = ['name' => $source->name, 'new' => 0, 'ok' => false];
                Log::warning("[checkAll] {$source->name}: {$e->getMessage()}");
            }
        }

        $msg = $totalNew > 0
            ? "{$totalNew} nuevas noticias encontradas en " . count($sources) . ' fuentes.'
            : 'Rastreo completado — sin noticias nuevas. Las fuentes pueden estar sin actualizar o las URLs del seeder necesitan ajuste.';

        return response()->json([
            'success'   => 1,
            'message'   => $msg,
            'total_new' => $totalNew,
            'results'   => $results,
        ]);
    }

    /**
     * PATCH /api/editorial/sources/{id}
     * Actualiza nombre y/o URL de una fuente editorial.
     */
    public function updateSource(Request $request, int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url'  => 'sometimes|url|max:500',
        ]);

        $source = NewsSource::findOrFail($id);
        $source->update($validated);

        return response()->json([
            'success' => 1,
            'message' => 'Fuente actualizada.',
            'data'    => $source,
        ]);
    }

    /**
     * POST /api/editorial/sources/detect
     * Analiza una URL y detecta si tiene RSS o hay que hacer scraping.
     * Body: { "url": "https://..." }
     */
    public function detectSource(Request $request): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate(['url' => 'required|url|max:500']);
        $url       = rtrim($validated['url'], '/');

        try {
            $client = new \GuzzleHttp\Client([
                'timeout'         => 15,
                'connect_timeout' => 8,
                'verify'          => false,
                'headers'         => ['User-Agent' => 'CinemaClub-Bot/1.0 (cinemaclub.es)'],
                'allow_redirects' => ['max' => 5],
            ]);
            $html = (string) $client->get($url)->getBody();
        } catch (\Throwable $e) {
            return response()->json([
                'success' => 0,
                'message' => 'No se pudo acceder a la URL: ' . $e->getMessage(),
            ], 422);
        }

        $name   = $this->extractPageTitle($html) ?: parse_url($url, PHP_URL_HOST);
        $rssUrl = $this->findRssLinkInHtml($html, $url) ?? $this->probeRssPaths($url, $client);

        if ($rssUrl) {
            return response()->json([
                'success'      => 1,
                'type'         => 'rss',
                'detected_url' => $rssUrl,
                'name'         => $name,
            ]);
        }

        return response()->json([
            'success'         => 1,
            'type'            => 'scraping',
            'name'            => $name,
            'selector_config' => $this->suggestSelectors($html),
        ]);
    }

    /**
     * POST /api/editorial/sources
     * Crea una nueva fuente editorial.
     */
    public function createSource(Request $request): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $validated = $request->validate([
            'name'                 => 'required|string|max:255',
            'url'                  => 'required|url|max:500',
            'type'                 => 'required|in:rss,scraping,sitemap',
            'purpose'              => 'nullable|in:news,events',
            'check_interval_hours' => 'nullable|integer|min:1|max:168',
            'selector_config'      => 'nullable|array',
        ]);

        $source = NewsSource::create([
            'name'                 => $validated['name'],
            'url'                  => $validated['url'],
            'type'                 => $validated['type'],
            'purpose'              => $validated['purpose'] ?? 'news',
            'check_interval_hours' => $validated['check_interval_hours'] ?? 12,
            'is_active'            => true,
            'selector_config'      => $validated['selector_config'] ?? null,
        ]);

        return response()->json([
            'success' => 1,
            'message' => "Fuente \"{$source->name}\" creada correctamente.",
            'data'    => [
                'id'                   => $source->id,
                'name'                 => $source->name,
                'url'                  => $source->url,
                'type'                 => $source->type,
                'is_active'            => $source->is_active,
                'check_interval_hours' => $source->check_interval_hours,
                'last_checked_at'      => null,
                'items_today'          => 0,
                'total_items'          => 0,
                'needs_review'         => false,
                'failed_attempts'      => 0,
                'last_error'           => null,
            ],
        ], 201);
    }

    /**
     * PATCH /api/editorial/sources/{id}/toggle
     * Activa/pausa una fuente.
     */
    public function toggleSource(int $id): JsonResponse
    {
        if ($err = $this->authorizeEditor()) return $err;

        $source = NewsSource::findOrFail($id);
        $source->update(['is_active' => ! $source->is_active]);

        return response()->json([
            'success'   => 1,
            'is_active' => $source->is_active,
            'message'   => $source->is_active ? 'Fuente activada.' : 'Fuente pausada.',
        ]);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function extractPageTitle(string $html): string
    {
        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)["\']/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
        }
        return '';
    }

    private function findRssLinkInHtml(string $html, string $baseUrl): ?string
    {
        $patterns = [
            '/<link[^>]+type=["\']application\/(?:rss|atom)\+xml["\'][^>]+href=["\']([^"\']+)["\']/i',
            '/<link[^>]+href=["\']([^"\']+)["\'][^>]+type=["\']application\/(?:rss|atom)\+xml["\']/i',
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $html, $m)) {
                return $this->toAbsoluteUrl($m[1], $baseUrl);
            }
        }
        return null;
    }

    private function probeRssPaths(string $baseUrl, \GuzzleHttp\Client $client): ?string
    {
        $root  = parse_url($baseUrl, PHP_URL_SCHEME) . '://' . parse_url($baseUrl, PHP_URL_HOST);
        $paths = ['/feed', '/rss', '/rss.xml', '/feed.xml', '/atom.xml', '/feed/rss2'];
        foreach ($paths as $path) {
            try {
                $body = (string) $client->get($root . $path, ['timeout' => 5])->getBody();
                if (str_contains($body, '<rss') || str_contains($body, '<feed') || str_contains($body, '<channel>')) {
                    return $root . $path;
                }
            } catch (\Throwable) {
                // sigue probando
            }
        }
        return null;
    }

    private function suggestSelectors(string $html): array
    {
        // Detecta el contenedor de artículos más probable
        $knownClasses = ['caja-evento', 'entrada-novedad', 'asset-abstract', 'news-item', 'article-item', 'post-item', 'entry-item'];
        $items = 'article, .entry, .post, .item';
        foreach ($knownClasses as $cls) {
            if (str_contains($html, "class=\"$cls") || str_contains($html, "class='$cls")) {
                $items = ".$cls";
                break;
            }
        }
        if ($items === 'article, .entry, .post, .item' && substr_count($html, '<article') > 2) {
            $items = 'article';
        }
        return [
            'items'       => $items,
            'title'       => 'h1 a, h2 a, h3 a, .titulo, .title',
            'link'        => 'h1 a, h2 a, h3 a, a',
            'description' => '.excerpt, .intro, .description, .resumen, p',
        ];
    }

    private function toAbsoluteUrl(string $url, string $base): string
    {
        if (str_starts_with($url, 'http')) return $url;
        $root = parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST);
        return $root . (str_starts_with($url, '/') ? $url : '/' . $url);
    }

    private function buildPostContent(string $summary, string $sourceUrl, string $sourceName): string
    {
        $safeUrl     = htmlspecialchars($sourceUrl, ENT_QUOTES, 'UTF-8');
        $safeName    = htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8');
        $safeSummary = nl2br(htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'));

        return <<<HTML
<p>{$safeSummary}</p>
<p><em>Fuente: <a href="{$safeUrl}" target="_blank" rel="noopener noreferrer">{$safeName}</a></em></p>
HTML;
    }
}
