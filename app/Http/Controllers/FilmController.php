<?php

namespace App\Http\Controllers;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FilmController extends Controller
{
    public function index()
    {
        return Film::orderBy('release_date', 'desc')->paginate(20);
    }

    public function show(Film $film)
    {
        return response()->json($film);
    }

    // Admin: Crear película manualmente por si se necesita corregir algún error de manera manual, aunque la lógica sea automatizada
    public function store(Request $request)
    {
        $validated = $request->validate([ // Validaciones para llenado manual
            'tmdb_id' => 'nullable|integer|unique:films,tmdb_id',
            'wikidata_id' => 'nullable|integer|unique:films,wikidata_id',

            'title' => 'required|string|max:255',
            'original_title' => 'required|string|max:255',
            'genre' => 'nullable|string|max:100',
            'origin_country' => 'nullable|string|max:100',
            'original_language' => 'nullable|string|max:100',
            'overview' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'release_date' => 'nullable|date',
            'frame' => 'nullable|url',

            'awards' => 'nullable|array',
            'nominations' => 'nullable|array',
            'festivals' => 'nullable|array',

            'total_awards' => 'nullable|integer|min:0',
            'total_nominations' => 'nullable|integer|min:0',
            'total_festivals' => 'nullable|integer|min:0',

            // Tomar los datos del cast de cast_crew
            'director_id' => 'required|exists:cast_crew,idPerson',
            
            'cast' => 'nullable|array', 
            'cast.*.idPerson' => 'required|exists:cast_crew,idPerson',
            'cast.*.role' => 'required|string|max:100',
            'cast.*.character_name' => 'nullable|string|max:255',
            'cast.*.credit_order' => 'nullable|integer|min:0',
            'cast.*.photo' =>'nullable|url',
            
            'vote_average' => 'nullable|numeric|min:0|max:10', // Nota media calculada a partir de los puntos dados por usuarios registrados
            'individualRate' => 'nullable|numeric|min:0|max:10', // Nota del usuario logueado
            'globalRate' => 'nullable|numeric|min:0|max:10', // Nota global guardada en la API TMDB
        ]);

        // Convertimos arrays a JSON antes de guardar
        $validated['awards'] = json_encode($validated['awards'] ?? []);
        $validated['nominations'] = json_encode($validated['nominations'] ?? []);
        $validated['festivals'] = json_encode($validated['festivals'] ?? []);

        $film = Film::create($validated);

        // Guardar director en pivot
        \DB::table('film_cast_pivot')->insert([
            'idFilm' => $film->idFilm,
            'idPerson' => $validated['director_id'],
            'role' => 'Director'
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


    // Actualización película Nota: Rellenamos con metadatos en el caso de imágenes de cartel de la película
    public function update(Request $request, Film $film)
    {
        $validated = $request->validate([
            'tmdb_id' => 'nullable|integer|unique:films,tmdb_id',
            'wikidata_id' => 'nullable|integer|unique:films,wikidata_id',
            
            'title' => 'required|string|max:255',
            'original_title' => 'required|string|max:255',
            'genre' => 'nullable|string|max:100',
            'origin_country' => 'nullable|string|max:100',
            'original_language' => 'nullable|string|max:100',
            'overview' => 'nullable|string',
            'duration' => 'nullable|integer|min:1',
            'release_date' => 'nullable|date',
            'frame' => 'nullable|url',

            'awards' => 'nullable|array',
            'nominations' => 'nullable|array',
            'festivals' => 'nullable|array',

            'total_awards' => 'nullable|integer|min:0',
            'total_nominations' => 'nullable|integer|min:0',
            'total_festivals' => 'nullable|integer|min:0',

            // Tomar los datos del cast de cast_crew
            'director_id' => 'required|exists:cast_crew,idPerson',
            
            'cast' => 'nullable|array', 
            'cast.*.idPerson' => 'required|exists:cast_crew,idPerson',
            'cast.*.role' => 'required|string|max:100',
            'cast.*.character_name' => 'nullable|string|max:255',
            'cast.*.credit_order' => 'nullable|integer|min:0',
            'cast.*.photo' =>'nullable|url',
            
            'vote_average' => 'nullable|numeric|min:0|max:10', // Nota media calculada a partir de los puntos dados por usuarios registrados
            'individualRate' => 'nullable|numeric|min:0|max:10', // Nota del usuario logueado
            'globalRate' => 'nullable|numeric|min:0|max:10', // Nota global guardada en la API TMDB
        ]);

        $validated['awards'] = json_encode($validated['awards'] ?? []);
        $validated['nominations'] = json_encode($validated['nominations'] ?? []);
        $validated['festivals'] = json_encode($validated['festivals'] ?? []);

        $film->update($validated);

        // Actualizar director y cast → limpiamos primero
        \DB::table('film_cast_pivot')->where('idFilm', $film->idFilm)->delete();

        \DB::table('film_cast_pivot')->insert([
            'idFilm' => $film->idFilm,
            'idPerson' => $validated['director_id'],
            'role' => 'Director'
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

