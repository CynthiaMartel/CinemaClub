<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\CastCrew;
use Illuminate\Support\Facades\Log;

use App\Http\Controllers\FilmDataController;

class TestsDBApisController extends Controller
{
    // -------------------------------------------------------------------------------
    // FUNCIÓN DE PRUEBA para POSTMAN: traer 2 películas para prueba rápida
    public function testTMDb($year)
    {
        $apiKey = config('services.tmdb.key');

        if (!$apiKey) {
            return response()->json(['error' => 'Falta la clave API de TMDb!'], 500);
        }

        $url = "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&primary_release_year={$year}&sort_by=popularity.desc&page=1";
        $response = Http::get($url);

        if (!$response->successful()) {
            return response()->json(['error' => 'Error al conectar con TMDb!'], 500);
        }

        $movies = array_slice($response->json()['results'] ?? [], 0, 2); // Traer solo 2 películas 
        $testData = [];

        foreach ($movies as $movie) {
            try {
                $detailsResponse = Http::get(
                    "https://api.themoviedb.org/3/movie/{$movie['id']}?api_key=$apiKey&append_to_response=credits,external_ids"
                );

                if (!$detailsResponse->successful()) continue;

                $details = $detailsResponse->json();

                // Campos TMDb
                $castData = [];
                foreach (array_slice($details['credits']['cast'] ?? [], 0, 5) as $actor) {
                    // Traer datos de reparto y dirección
                    $personDetails = Http::get(
                        "https://api.themoviedb.org/3/person/{$actor['id']}?api_key=$apiKey"
                    )->json();

                    $castData[] = [
                        'name' => $actor['name'],
                        'character' => $actor['character'] ?? null,
                        'profile_path' => isset($personDetails['profile_path']) ? "https://image.tmdb.org/t/p/w500" . $personDetails['profile_path'] : null,
                        'bio' => $personDetails['biography'] ?? null,
                        'birthday' => $personDetails['birthday'] ?? null,
                        'place_of_birth' => $personDetails['place_of_birth'] ?? null,
                    ];
                }

                $directorData = collect($details['credits']['crew'] ?? [])
                    ->firstWhere('job', 'Director'); // Buscar director/directora

                $directorDetails = [];
                if ($directorData && !empty($directorData['id'])) {
                    $personDetailsDirector = Http::get(
                        "https://api.themoviedb.org/3/person/{$directorData['id']}?api_key=$apiKey"
                    )->json();

                    $directorDetails = [
                        'name' => $directorData['name'],
                        'profile_path' => isset($personDetailsDirector['profile_path']) ? "https://image.tmdb.org/t/p/w500" . $personDetailsDirector['profile_path'] : null,
                        'bio' => $personDetailsDirector['biography'] ?? null,
                        'birthday' => $personDetailsDirector['birthday'] ?? null,
                        'place_of_birth' => $personDetailsDirector['place_of_birth'] ?? null,
                    ];
                }

                $genres = implode(', ', array_column($details['genres'] ?? [], 'name'));
                $countries = implode(', ', array_column($details['production_countries'] ?? [], 'name'));

                // Wikidata
                $wikidataId = $details['external_ids']['wikidata_id'] ?? null;

                // Si TMDb no da wikidata_id, buscar por título en inglés
                if (!$wikidataId) {
                    $wikidataId = $this->findWikidataIdByTitle($details['original_title'] ?? $details['title'] ?? '');
                }

                $wikidata = ['awards' => [], 'nominations' => [], 'festivals' => []];

                if ($wikidataId) {
                    try {
                        Log::info("🔍 Encontrado Wikidata ID para " . ($details['original_title'] ?? $details['title']) . ": " . $wikidataId);
                        $wikidata = $this->getWikidataData($wikidataId);
                    } catch (\Throwable $e) {
                        Log::warning("Fallo al obtener Wikidata para " . ($details['original_title'] ?? $details['title']) . ": " . $e->getMessage());
                    }
                } else {
                    Log::info("No se encontró ID de Wikidata para " . ($details['original_title'] ?? $details['title']));
                }

                // Asegurar de que sean arrays
                $awards = $wikidata['awards'] ?? [];
                $nominations = $wikidata['nominations'] ?? [];
                $festivals = $wikidata['festivals'] ?? [];

                $testData[] = [
                    'tmdb_id' => $movie['id'],
                    'title' => $details['title'],
                    'original_title' => $details['original_title'] ?? null,
                    'wikidata_id' => $wikidataId,
                    'director' => $directorDetails,
                    'cast' => $castData,
                    'genres' => $genres,
                    'countries' => $countries,
                    'release_date' => $details['release_date'] ?? null,
                    'awards' => $wikidata['awards'],
                    'nominations' => $wikidata['nominations'],
                    'festivals' => $wikidata['festivals'],
                    'total_awards' => count($awards),
                    'total_nominations' => count($nominations),
                    'total_festivals' => count($festivals),
                ];
            } catch (\Throwable $e) {
                Log::error("Error procesando película de prueba: " . $e->getMessage());
                continue;
            }
        }

        return response()->json($testData);
    }

    // FUNCIÓN DE PRUEBA WIKIDATA para POSTMAN:
    public function testWikidata($wikidataId)
    {
        return $this->getWikidataData($wikidataId); // Nota: Los métodos privados se llaman con this para poder acceder a ellos
    }

    // Wikidata: para saber si busca por titulo
    public function testFindWikidataIdByTitle($title)
    {
        try {
            $id = $this->findWikidataIdByTitle($title);

            if (!$id) {
                return response()->json([
                    'title' => $title,
                    'wikidata_id' => null,
                    'message' => "No se encontró entrada en Wikidata para este título."
                ]);
            }

            $data = $this->getWikidataData($id);

            return response()->json([
                'title' => $title,
                'wikidata_id' => $id,
                'awards' => $data['awards'] ?? [],
                'nominations' => $data['nominations'] ?? [],
                'festivals' => $data['festivals'] ?? []
            ]);
        } catch (\Throwable $e) {
            \Log::error("Error en testFindWikidataIdByTitle: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // -------------------------------------------------------------------------------
    

        // API WIKIDATA: Obtener datos faltantes que no proporciona TMDB
    private function getWikidataData($wikidataId)
    {
        if (!$wikidataId) {
            return ['awards' => [], 'nominations' => [], 'festivals' => []];
        }

        $query = urlencode("
        SELECT ?awardLabel ?nomLabel ?festivalLabel WHERE {
        OPTIONAL { wd:$wikidataId wdt:P166 ?award. }     
        OPTIONAL { wd:$wikidataId wdt:P1411 ?nom. }       
        OPTIONAL { wd:$wikidataId wdt:P1433 ?festival. } 
        SERVICE wikibase:label { bd:serviceParam wikibase:language 'en,es'. }
        }");

        $url = "https://query.wikidata.org/sparql?query=$query&format=json";

        $response = Http::withHeaders([
            'Accept' => 'application/json'
        ])->get($url);

        if (!$response->successful()) {
            Log::warning("Fallo en consulta Wikidata para ID $wikidataId");
            return ['awards' => [], 'nominations' => [], 'festivals' => []];
        }

        $data = $response->json();
        $awards = [];
        $nominations = [];
        $festivals = [];

        foreach ($data['results']['bindings'] ?? [] as $bind) {
            if (!empty($bind['awardLabel']['value'])) $awards[] = $bind['awardLabel']['value'];
            if (!empty($bind['nomLabel']['value'])) $nominations[] = $bind['nomLabel']['value'];
            if (!empty($bind['festivalLabel']['value'])) $festivals[] = $bind['festivalLabel']['value'];
        }

        return [
            'awards' => array_values(array_unique($awards)),
            'nominations' => array_values(array_unique($nominations)),
            'festivals' => array_values(array_unique($festivals)),
        ];
    }

    // Por si TMDB no proporciona el ID de Wikidata
    private function findWikidataIdByTitle($title)
    {
        if (!$title) return null;

        $query = urlencode("
        SELECT ?item WHERE {
        ?item rdfs:label \"$title\"@en.
        SERVICE wikibase:label { bd:serviceParam wikibase:language 'en,es'. }
        } LIMIT 1
        ");

        $url = "https://query.wikidata.org/sparql?query=$query&format=json";

        $response = Http::timeout(10)->get($url);

        if ($response->successful() && !empty($response['results']['bindings'][0]['item']['value'])) {
            return basename($response['results']['bindings'][0]['item']['value']); // Devuelve Qxxxx
        }

        return null;
    }

}

