<?php

namespace App\Http\Controllers;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\FilmRequest;
use Illuminate\Database\QueryException;
use App\Services\AzureTranslatorService;

class FilmController extends Controller
{


    /**
     * Búsqueda de films para barra de búsqueda o para implementar filtros
     * -- Cualquier usuario: puede buscar en barra de búsqueda por título / título original y hay filtros concretos por género y año
     * - - Admin / Editor: lo mismo pero además puede buscar por idFilm, tmdb_id, wikidata_id, género, país, idioma, etc.
     */
    public function search(Request $request)
    {
        $currentUser = $request->user();
        $IsAdminOrEditor = $currentUser && $currentUser->isAdminOrEditor();

        $q = trim($request->get('q', ''));

        $query = Film::query();

        if ($q !== '') {
            $query->where(function ($sub) use ($q, $IsAdminOrEditor) {
                // Búsqueda "pública" para todos los users: título y título original
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('original_title', 'like', "%{$q}%");

                if ($IsAdminOrEditor) {
                    // Si es ADMIN  o EDITOR  ampliamos búsqueda
                    // Nota: ID numéricos
                    if (is_numeric($q)) {
                        $idInt = (int) $q;
                        $sub->orWhere('idFilm', $idInt)
                            ->orWhere('tmdb_id', $idInt)
                            ->orWhere('wikidata_id', $idInt);
                    }

                    // Campos de texto más detallados o "técnicos"
                    $sub->orWhere('genre', 'like', "%{$q}%")
                        ->orWhere('origin_country', 'like', "%{$q}%")
                        ->orWhere('original_language', 'like', "%{$q}%");
                }
            });
        }

        // Filtros adicionales disponibles para TODOS los usuarios (por género, año o década)
        if ($request->filled('genre')) {
            $genre = trim($request->get('genre'));
            $query->where('genre', 'like', '%' . $genre . '%');
        }

        if ($request->filled('year')) {
            $year = (int) $request->get('year');
            if ($year > 1800 && $year < 2100) {
                $query->whereYear('release_date', $year);
            }
        }

        // decade: se espera un valor como 1980, 1990, 2000, etc. ** Mejorar tal vez para desplegar opciones en fronted para años concretos
        if ($request->filled('decade')) {
            $decade = (int) $request->get('decade');
            if ($decade > 1800 && $decade < 2100) {
                $start = $decade;
                $end   = $decade + 9;

                $query->whereYear('release_date', '>=', $start)
                    ->whereYear('release_date', '<=', $end);
            }
        }

        // Filtros adicionales SOLO para ADMIN Y EDITOR
        if ($IsAdminOrEditor) {
            if ($request->filled('country')) {
                $query->where('origin_country', 'like', '%' . trim($request->get('country')) . '%');
            }

            if ($request->filled('language')) {
                $query->where('original_language', 'like', '%' . trim($request->get('language')) . '%');
            }

            // Rango de años opcional *
            if ($request->filled('year_from')) {
                $yearFrom = (int) $request->get('year_from');
                $query->whereYear('release_date', '>=', $yearFrom);
            }

            if ($request->filled('year_to')) {
                $yearTo = (int) $request->get('year_to');
                $query->whereYear('release_date', '<=', $yearTo);
            }
        }

        $films = $query
            ->orderBy('release_date', 'desc')
            ->limit(20)
            ->get();

        // Búsqueda más ligera para la barra de búsqueda
        $formatted = $films->map(function (Film $film) {
            return [
                'idFilm'         => $film->idFilm,
                'title'          => $film->title,
                'original_title' => $film->original_title,
                'year'           => $film->release_date ? substr($film->release_date, 0, 4) : null,
                'genre'          => $film->genre,
                'frame'          => $film->frame,
                'vote_average'   => $film->vote_average,
            ];
        });

        return response()->json([
            'success' => 1,
            'data'    => $formatted,
        ]);
    }


    /**
     * Listado paginado de films ordenados de más reciente a más antiguo
     * GET /films?page=1&per_page=24
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 24), 60);

        $films = Film::orderBy('created_at', 'desc')
            ->paginate($perPage);

        $films->getCollection()->transform(function (Film $film) {
            return [
                'idFilm'         => $film->idFilm,
                'title'          => $film->title,
                'original_title' => $film->original_title,
                'year'           => $film->release_date ? substr($film->release_date, 0, 4) : null,
                'genre'          => $film->genre,
                'frame'          => $film->frame,
                'vote_average'   => $film->vote_average,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $films,
        ]);
    }

    // Búsqueda por id de film para película concreta
    public function show(Film $film)
    {
        $film->load('cast');

        return response()->json($film);
    }

    public function translateOverview(Film $film)
    {
        if (!$film->overview) {
            return response()->json(['error' => 'Sin sinopsis original.'], 422);
        }

        if ($film->overview_es) {
            return response()->json(['overview_es' => $film->overview_es]);
        }

        try {
            $translated = app(AzureTranslatorService::class)->translate($film->overview);
            if (!$translated) {
                return response()->json(['error' => 'No se pudo traducir.'], 502);
            }
            $film->overview_es = $translated;
            $film->saveQuietly();
            return response()->json(['overview_es' => $translated]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error en el servicio de traducción.'], 502);
        }
    }

    // Admin: Crear película manualmente por si se necesita añadir alguna película que no se haya encontrado con API TMDB ni Wikidata (de manera manual desde ADMIN)
    public function store(FilmRequest $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $validated = $request->validated();

        // Convertimos arrays a JSON antes de guardar
        $validated['awards']     = json_encode($validated['awards'] ?? []);
        $validated['nominations']= json_encode($validated['nominations'] ?? []);
        $validated['festivals']  = json_encode($validated['festivals'] ?? []);

        try {
            $film = Film::create($validated);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'success' => 0,
                    'message' => 'Ya existe una película con ese título y fecha de estreno.',
                    'errors'  => ['title' => ['Ya existe una película con ese título y esa fecha de estreno.']],
                ], 422);
            }
            throw $e;
        }

        // Guardar director en tabla pivot si se proporcionó
        if (!empty($validated['director_id'])) {
            \DB::table('film_cast_pivot')->insert([
                'idFilm'   => $film->idFilm,
                'idPerson' => $validated['director_id'],
                'role'     => 'Director',
            ]);
        }

        // Guardar cast si existe
        if (!empty($validated['cast'])) {
            foreach ($validated['cast'] as $cast) {
                $cast['idFilm'] = $film->idFilm;
                \DB::table('film_cast_pivot')->insert($cast);
            }
        }

        return response()->json(['success' => 1, 'data' => $film->fresh()->load('cast')], 201);
    }


    // Actualización película desde ADMIN de manera manual
    public function update(FilmRequest $request, Film $film)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $validated = $request->validated();

        $validated['awards']     = json_encode($validated['awards'] ?? []);
        $validated['nominations']= json_encode($validated['nominations'] ?? []);
        $validated['festivals']  = json_encode($validated['festivals'] ?? []);

        try {
            $film->update($validated);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json([
                    'success' => 0,
                    'message' => 'Ya existe una película con ese título y fecha de estreno.',
                    'errors'  => ['title' => ['Ya existe una película con ese título y esa fecha de estreno.']],
                ], 422);
            }
            throw $e;
        }

        // Actualizar pivot: limpiamos director anterior y re-insertamos
        \DB::table('film_cast_pivot')->where('idFilm', $film->idFilm)->where('role', 'Director')->delete();

        if (!empty($validated['director_id'])) {
            \DB::table('film_cast_pivot')->insert([
                'idFilm'   => $film->idFilm,
                'idPerson' => $validated['director_id'],
                'role'     => 'Director',
            ]);
        }

        if (!empty($validated['cast'])) {
            \DB::table('film_cast_pivot')->where('idFilm', $film->idFilm)->where('role', '!=', 'Director')->delete();
            foreach ($validated['cast'] as $cast) {
                $cast['idFilm'] = $film->idFilm;
                \DB::table('film_cast_pivot')->insert($cast);
            }
        }

        return response()->json(['success' => 1, 'data' => $film->fresh()->load('cast')]);
    }

    public function destroy(Request $request, Film $film)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $film->delete();
        return response()->json(['success' => 1, 'message' => 'Película eliminada']);
    }

    /**
     * Búsqueda de personas en cast_crew para autocompletado del formulario admin.
     * GET /admin/cast-search?q=Kubrick
     */
    public function castSearch(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['success' => 1, 'data' => []]);
        }

        $people = \DB::table('cast_crew')
            ->where(function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
                if (is_numeric($q)) {
                    $query->orWhere('idPerson', (int) $q);
                }
            })
            ->select('idPerson', 'name', 'photo')
            ->limit(15)
            ->get();

        return response()->json(['success' => 1, 'data' => $people]);
    }

    /**
     * Crea una nueva persona en cast_crew desde el panel admin.
     * POST /api/admin/cast-crew/store
     */
    public function castPersonStore(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'photo'   => ['nullable', 'url', 'max:225'],
            'tmdb_id' => ['nullable', 'integer', 'unique:cast_crew,tmdb_id'],
        ]);

        $id = \DB::table('cast_crew')->insertGetId([
            'name'       => $validated['name'],
            'photo'      => $validated['photo'] ?? null,
            'tmdb_id'    => $validated['tmdb_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $person = \DB::table('cast_crew')->where('idPerson', $id)->first();

        return response()->json(['success' => 1, 'data' => $person], 201);
    }

    /**
     * Actualiza datos de una persona en cast_crew desde el panel admin.
     * PUT /api/admin/cast-crew/{id}/update
     */
    public function castPersonUpdate(Request $request, int $id)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $person = \DB::table('cast_crew')->where('idPerson', $id)->first();
        if (!$person) {
            return response()->json(['success' => 0, 'message' => 'Persona no encontrada'], 404);
        }

        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:255'],
            'photo'   => ['nullable', 'url', 'max:225'],
            'tmdb_id' => ['nullable', 'integer', "unique:cast_crew,tmdb_id,{$id},idPerson"],
        ]);

        \DB::table('cast_crew')->where('idPerson', $id)->update([
            'name'       => $validated['name'],
            'photo'      => $validated['photo'] ?? null,
            'tmdb_id'    => $validated['tmdb_id'] ?? null,
            'updated_at' => now(),
        ]);

        $person = \DB::table('cast_crew')->where('idPerson', $id)->first();

        return response()->json(['success' => 1, 'data' => $person]);
    }

    /**
     * Obtiene datos de una película desde la API de TMDB y los mapea a nuestro modelo.
     * GET /api/admin/tmdb-fetch?tmdb_id=12345
     */
    public function tmdbFetch(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->isAdminOrEditor()) {
            return response()->json(['success' => 0, 'message' => 'No autorizado'], 403);
        }

        $tmdbId = (int) $request->get('tmdb_id');
        if (!$tmdbId) {
            return response()->json(['success' => 0, 'message' => 'tmdb_id requerido'], 422);
        }

        $apiKey  = config('services.tmdb.key');
        $base    = 'https://api.themoviedb.org/3';
        $imgBase = 'https://image.tmdb.org/t/p/w500';
        $imgBd   = 'https://image.tmdb.org/t/p/w1280';
        $imgPerson = 'https://image.tmdb.org/t/p/w185';

        $movieRes = Http::timeout(10)->get("{$base}/movie/{$tmdbId}", [
            'api_key'  => $apiKey,
            'language' => 'es-ES',
        ]);

        if ($movieRes->status() === 404) {
            return response()->json(['success' => 0, 'message' => 'Película no encontrada en TMDB'], 404);
        }
        if (!$movieRes->successful()) {
            return response()->json(['success' => 0, 'message' => 'Error al contactar TMDB'], 502);
        }

        $movie = $movieRes->json();

        $creditsRes = Http::timeout(10)->get("{$base}/movie/{$tmdbId}/credits", ['api_key' => $apiKey]);
        $credits = $creditsRes->successful() ? $creditsRes->json() : [];

        $genres    = implode(', ', array_column($movie['genres'] ?? [], 'name'));
        $countries = implode(', ', array_column($movie['production_countries'] ?? [], 'iso_3166_1'));

        $filmData = [
            'tmdb_id'           => $tmdbId,
            'title'             => $movie['title'] ?? $movie['original_title'] ?? '',
            'original_title'    => $movie['original_title'] ?? '',
            'overview'          => $movie['overview'] ?? '',
            'duration'          => $movie['runtime'] ?: null,
            'release_date'      => $movie['release_date'] ?: null,
            'vote_average'      => round((float)($movie['vote_average'] ?? 0), 1),
            'genre'             => $genres,
            'origin_country'    => $countries,
            'original_language' => $movie['original_language'] ?? '',
            'frame'             => isset($movie['poster_path'])   ? $imgBase . $movie['poster_path']   : null,
            'backdrop'          => isset($movie['backdrop_path']) ? $imgBd   . $movie['backdrop_path'] : null,
        ];

        // Director: buscar en cast_crew por tmdb_id
        $tmdbDirector = collect($credits['crew'] ?? [])->firstWhere('job', 'Director');
        $directorData = null;
        if ($tmdbDirector) {
            $dbPerson = \DB::table('cast_crew')->where('tmdb_id', $tmdbDirector['id'])->first();
            $directorData = [
                'tmdb_id'  => $tmdbDirector['id'],
                'name'     => $tmdbDirector['name'],
                'photo'    => $tmdbDirector['profile_path'] ? $imgPerson . $tmdbDirector['profile_path'] : null,
                'idPerson' => $dbPerson?->idPerson ?? null,
            ];
        }

        // Cast (top 10): buscar en cast_crew por tmdb_id
        $castData = [];
        foreach (array_slice($credits['cast'] ?? [], 0, 10) as $member) {
            $dbPerson = \DB::table('cast_crew')->where('tmdb_id', $member['id'])->first();
            $castData[] = [
                'tmdb_id'   => $member['id'],
                'name'      => $member['name'],
                'photo'     => $member['profile_path'] ? $imgPerson . $member['profile_path'] : null,
                'character' => $member['character'] ?? '',
                'order'     => $member['order'] ?? 0,
                'idPerson'  => $dbPerson?->idPerson ?? null,
            ];
        }

        return response()->json([
            'success' => 1,
            'data' => [
                'film'     => $filmData,
                'director' => $directorData,
                'cast'     => $castData,
            ],
        ]);
    }

    /**
     * Plataformas de streaming por país (via TMDB Watch Providers)
     * GET /films/{id}/watch-providers
     * Resultado cacheado 24h por película.
     */
    public function watchProviders($id)
    {
        $film = Film::findOrFail($id);

        if (!$film->tmdb_id) {
            return response()->json(['success' => 0, 'data' => []]);
        }

        $countries = ['MX', 'AR', 'CO', 'CL', 'PE', 'EC', 'UY', 'VE', 'ES'];
        $cacheKey  = "watch_providers_{$film->tmdb_id}";

        $data = Cache::remember($cacheKey, now()->addHours(24), function () use ($film, $countries) {
            $apiKey   = config('services.tmdb.key');
            $response = Http::timeout(8)->get(
                "https://api.themoviedb.org/3/movie/{$film->tmdb_id}/watch/providers?api_key={$apiKey}"
            );

            if (!$response->successful()) {
                return [];
            }

            $results = $response->json('results', []);
            $filtered = [];

            foreach ($countries as $code) {
                if (!isset($results[$code])) continue;

                $entry = $results[$code];
                $filtered[$code] = [
                    'link'     => $entry['link'] ?? null,
                    'flatrate' => $this->mapProviders($entry['flatrate'] ?? []),
                    'rent'     => $this->mapProviders($entry['rent']     ?? []),
                    'buy'      => $this->mapProviders($entry['buy']      ?? []),
                ];
            }

            return $filtered;
        });

        return response()->json(['success' => 1, 'data' => $data]);
    }

    private function mapProviders(array $providers): array
    {
        return array_map(fn($p) => [
            'id'       => $p['provider_id'],
            'name'     => $p['provider_name'],
            'logo'     => 'https://image.tmdb.org/t/p/original' . $p['logo_path'],
            'priority' => $p['display_priority'],
        ], $providers);
    }

    /**
     * Estado de la cola de jobs para el monitor de importación del panel admin.
     * GET /api/admin/queue-status
     */
    public function queueStatus(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->idRol != 1) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $pendingByClass = [];
        foreach (\DB::table('jobs')->select('payload')->get() as $job) {
            $payload   = json_decode($job->payload, true);
            $class     = $payload['displayName'] ?? 'Unknown';
            $parts     = explode('\\', $class);
            $shortName = end($parts);
            $pendingByClass[$shortName] = ($pendingByClass[$shortName] ?? 0) + 1;
        }

        $failedJobs = \DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(8)
            ->get()
            ->map(function ($job) {
                $payload   = json_decode($job->payload, true);
                $class     = $payload['displayName'] ?? 'Unknown';
                $parts     = explode('\\', $class);
                $firstLine = explode("\n", $job->exception ?? '');
                return [
                    'id'        => $job->uuid,
                    'class'     => end($parts),
                    'failed_at' => $job->failed_at,
                    'message'   => $firstLine[0] ?? '',
                ];
            });

        $recentlyImported = Film::select('idFilm', 'title', 'release_date', 'frame', 'created_at')
            ->where('created_at', '>=', now()->subDays(14))
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn ($f) => [
                'id'          => $f->idFilm,
                'title'       => $f->title,
                'year'        => $f->release_date ? $f->release_date->format('Y') : null,
                'frame'       => $f->frame,
                'imported_at' => $f->created_at->toISOString(),
            ]);

        $recentTotal = Film::where('created_at', '>=', now()->subDays(14))->count();

        return response()->json([
            'pending_total'      => \DB::table('jobs')->count(),
            'pending_by_class'   => $pendingByClass,
            'failed_total'       => \DB::table('failed_jobs')->count(),
            'failed_recent'      => $failedJobs,
            'recently_imported'  => $recentlyImported,
            'recent_total'       => $recentTotal,
            'checked_at'         => now()->toISOString(),
        ]);
    }
}


