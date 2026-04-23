<?php

namespace App\Http\Controllers;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\FilmRequest;
use Illuminate\Database\QueryException;

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
        return response()->json($film->load('cast')); //Para que devuelva la relación cast_crew y podamos ver director y reparto
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
            ->where('name', 'like', "%{$q}%")
            ->select('idPerson', 'name', 'photo')
            ->limit(15)
            ->get();

        return response()->json(['success' => 1, 'data' => $people]);
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
}


