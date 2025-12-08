<?php

namespace App\Http\Controllers;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Requests\FilmRequest;

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


    // Búsqueda por id de film para película concreta
    public function show(Film $film)
    {
        return response()->json($film);
    }

    // Admin: Crear película manualmente por si se necesita añadir alguna película que no se haya encontrado con API TMDB ni Wikidata (de manera manual desde ADMIN)
    public function store(FilmRequest $request)
    {
        $validated = $request->validated(); // Validaciones para llenado manual

        // Convertimos arrays a JSON antes de guardar
        $validated['awards']     = json_encode($validated['awards'] ?? []);
        $validated['nominations']= json_encode($validated['nominations'] ?? []);
        $validated['festivals']  = json_encode($validated['festivals'] ?? []);

        $film = Film::create($validated);

        // Guardar director en tabla pivot
        \DB::table('film_cast_pivot')->insert([
            'idFilm'   => $film->idFilm,
            'idPerson' => $validated['director_id'],
            'role'     => 'Director'
        ]);

        // Guardar cast si existe
        if (!empty($validated['cast'])) {
            foreach ($validated['cast'] as $cast) {
                $cast['idFilm'] = $film->idFilm;
                \DB::table('film_cast_pivot')->insert($cast);
            }
        }

        return response()->json($film, 201);
    }


    // Actualización película desde ADMIN de manera manual
    public function update(FilmRequest $request, Film $film)
    {
        $validated = $request->validated();

        $validated['awards']     = json_encode($validated['awards'] ?? []);
        $validated['nominations']= json_encode($validated['nominations'] ?? []);
        $validated['festivals']  = json_encode($validated['festivals'] ?? []);

        $film->update($validated);

        // Actualizar director y cast, limpiamos primero
        \DB::table('film_cast_pivot')->where('idFilm', $film->idFilm)->delete();

        \DB::table('film_cast_pivot')->insert([
            'idFilm'   => $film->idFilm,
            'idPerson' => $validated['director_id'],
            'role'     => 'Director'
        ]);

        if (!empty($validated['cast'])) {
            foreach ($validated['cast'] as $cast) {
                $cast['idFilm'] = $film->idFilm;
                \DB::table('film_cast_pivot')->insert($cast);
            }
        }

        return response()->json($film);
    }

    public function destroy(Film $film)
    {
        $film->delete();
        return response()->json(['message' => 'Película eliminada']);
    }  
}


