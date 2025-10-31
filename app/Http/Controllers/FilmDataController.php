<?php

namespace App\Http\Controllers;

use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\CastCrew;
use Illuminate\Support\Facades\Log;
use App\Jobs\ImportFilmsJob;

class FilmDataController extends Controller
{

// API TMDB + WIKIDATA --> LÓGICA PRINCIPAL que utiliza conexión a API TMDB para traer los datos a la tabla film y uso de API WIKIDATA  
// con callbacks a funciones que traen de esta API campos que no se podrían encontrar en TMDB 
// (A veces no se encuentran tampoco en WIKIDATA por películas poco conocidas o muy nuevas) 

public function importFromTMDB($yearStart, $yearEnd, $startPage = 1, $endPage = 1)

{
    $apiKey = config('services.tmdb.key'); // Guardado en config/services.php

    if (!$apiKey) {
        Log::error("Falta clave API de TMDb");
        return response()->json(['error' => 'Falta la clave API de TMDb'], 500);
    }

    $limit = 0; // PARA PRUEBAS: Manejar poniendo límite de 2-3 films y después --> ¡Cambiar a null o eliminar para traer todas las pelis!
    $insertadas = 0;
    $fallidas = 0;

    Log::info("Inicio de importación de films ($yearStart - $yearEnd)");

    // Cargar caché local de Wikidata IDs (título => QID) en storage/app/wikidata_cache.json
    $wikidataCache = $this->loadWikidataCache(); // Carga/guarda la caché local de Wikidata para evitar repetir consultas SPARQL en importaciones masivas.


    for ($page = $startPage; $page <= $endPage; $page++) { // Para peticiones a la página discovery de TMDB descubrir películas filtradas y ordenadas
                                                // (fechas, género, idioma, país, etc.), sin necesidad de buscar por título específico a diferencia de la pág. search
        $url = "https://api.themoviedb.org/3/discover/movie?api_key=$apiKey&primary_release_date.gte={$yearStart}-01-01&primary_release_date.lte={$yearEnd}-12-31&page=$page&sort_by=popularity.desc";
        $response = Http::timeout(10)->get($url); // timeout() para tener un tope de espera en la petición y sino, se pasa al siguiente

        if (!$response->successful()) {
            Log::warning("Fallo en la petición TMDb Discover: Página $page");
            continue;
        }

        $data = $response->json();
        if (empty($data['results'])) continue;

        // Nota: si limit es mayor que 0 (subir a más de 0 para pruebas), entonces se corta el array y trae esa cantidad de films, si limit = 0, trae todos los films
        foreach (($limit > 0 ? array_slice($data['results'], 0, $limit) : $data['results']) as $movie) { 

            try {
                // Comprobar si la película ya existe por rtitle y realease_date
                $existingFilm = Film::where('title', $movie['title'])
                    ->where('release_date', $movie['release_date'])
                    ->first();

                if ($existingFilm) {
                    Log::info("Película ya existente en BD: {$movie['title']}");
                    continue;
                }

                $detailsResponse = Http::timeout(10)->get(
                    "https://api.themoviedb.org/3/movie/{$movie['id']}?api_key=$apiKey&append_to_response=credits,external_ids"
                );

                if (!$detailsResponse->successful()) {
                    Log::warning("Fallo al obtener detalles de TMDb para ID {$movie['id']}");
                    $fallidas++;
                    continue;
                }

                $details = $detailsResponse->json();
                $castTMDb = $details['credits']['cast'] ?? [];
                $directorData = collect($details['credits']['crew'] ?? [])->firstWhere('job', 'Director');
                $directorId = null;

                // Guardar o recuperar director solo si realmente existe un ID y un nombre (Hay peículas que no tienen info del director)
                if (!empty($directorData['id']) && !empty($directorData['name'])) {
                    $director = CastCrew::firstOrCreate(
                        ['tmdb_id' => $directorData['id']],
                        [
                            'name' => $directorData['name'],
                            'bio' => null,
                            'profile_path' => $directorData['profile_path'] ?? null,
                        ]
                    );
                    $directorId = $director->idPerson;
                } else {
                    Log::info("Película '{$details['title']}' sin director válido (no insertado en pivot).");
                }

                $genres = implode(', ', array_column($details['genres'] ?? [], 'name'));
                $countries = implode(', ', array_column($details['production_countries'] ?? [], 'name'));

                // Buscar el wikidata_id proporcionado por TMDb; si no existe, se intentará obtener desde la BD o la cache wikidata_cache
                $wikidataId = $details['external_ids']['wikidata_id'] ?? null;

                // Si no hay wikidata_id desde TMDB;  comprobar si ya tenemos ese wikidata_id guardado en la BD 
                $filmWithSameTitle = Film::where('title', $details['title'])->first();
                if (!$wikidataId && $filmWithSameTitle && !empty($filmWithSameTitle->wikidata_id)) {
                    $wikidataId = $filmWithSameTitle->wikidata_id;
                    Log::info("Wikidata ID reutilizado desde BD para '{$details['title']}': {$wikidataId}");
                }

                // Si TMDb no devuelve wikidata_id ni existe en la BD; se buscará consultando SPARQL por el título original
                if (!$wikidataId) {
                    $originalTitle = $details['original_title'] ?? $details['title'] ?? null;

                    // comprobar caché por título antes de consultar SPARQL
                    if ($originalTitle && isset($wikidataCache[$originalTitle])) {
                        $wikidataId = $wikidataCache[$originalTitle];
                        Log::info("Wikidata ID obtenido de caché wikidata_cache para '{$originalTitle}': {$wikidataId}");
                    } else {
                        $wikidataId = $originalTitle ? $this->findWikidataIdByTitle($originalTitle) : null;
                        // guardar en caché si se encontró
                        if ($wikidataId && $originalTitle) {
                            $wikidataCache[$originalTitle] = $wikidataId;
                            $this->saveWikidataCache($wikidataCache);
                            Log::info("Wikidata_cache actualizado: '{$originalTitle}' => {$wikidataId}");
                        }
                    }
                }

                // Utilizar getWikiData(), para los datos que no nos ofrece TMDB (festivals, awards, nominations): función desarrollada más abajo
                $wikidata = ['awards' => [], 'nominations' => [], 'festivals' => []];
                $titleForLog = $details['original_title'] ?? $details['title'] ?? 'Sin título';

                if ($wikidataId) {
                    try {
                        Log::info("Encontrado Wikidata ID para {$titleForLog}: {$wikidataId}");
                        $wikidata = getWikidataData($wikidataId);
                    } catch (\Throwable $e) {
                        Log::warning("Fallo al obtener Wikidata para {$titleForLog}: " . $e->getMessage());
                    }
                } else {
                    Log::info("No se encontró ID de Wikidata para {$titleForLog}");
                }

                $awards = $wikidata['awards'] ?? [];
                $nominations = $wikidata['nominations'] ?? [];
                $festivals = $wikidata['festivals'] ?? [];

                Log::info("Insertando película en BD: {$details['title']}");

                $film = Film::create([
                    'tmdb_id' => $movie['id'],
                    'wikidata_id' => $wikidataId, 
                    'title' => $details['title'] ?? 'Sin título',
                    'original_title' => $details['original_title'] ?? null,
                    'genre' => $genres,
                    'origin_country' => $countries,
                    'original_language' => $details['original_language'] ?? '',
                    'overview' => $details['overview'] ?? '',
                    'duration' => $details['runtime'] ?? 0,
                    'release_date' => $details['release_date'] ?? now(),
                    'frame' => !empty($details['poster_path']) ? "https://image.tmdb.org/t/p/w500" . $details['poster_path'] : '',

                    'awards' => json_encode(array_slice($awards, 0, 4)),
                    'nominations' => json_encode(array_slice($nominations, 0, 4)),
                    'festivals' => json_encode(array_slice($festivals, 0, 4)),

                    'total_awards' => count($awards),
                    'total_nominations' => count($nominations),
                    'total_festivals' => count($festivals),
                    'director_id' => $directorId,

                    'vote_average' => $details['vote_average'] ?? 0,
                    'individualRate' => 0,
                    'globalRate' => 0,
                ]);

                // Solo insertar en pivot si el director fue creado o encontrado correctamente
                if ($directorId) {
                    DB::table('film_cast_pivot')->insert([
                        'idFilm' => $film->idFilm,
                        'idPerson' => $directorId,
                        'role' => 'Director'
                    ]);
                }

                // Guardar actores y su relación pivot solo si tienen datos válidos
                foreach ($castTMDb as $order => $actor) {
                    if (!empty($actor['id']) && !empty($actor['name'])) {
                        $castCrew = CastCrew::firstOrCreate(
                            ['tmdb_id' => $actor['id']],
                            [
                                'name' => $actor['name'] ?? 'Desconocido',
                                'profile_path' => $actor['profile_path'] ?? null,
                            ]
                        );

                        DB::table('film_cast_pivot')->insert([
                            'idFilm' => $film->idFilm,
                            'idPerson' => $castCrew->idPerson,
                            'role' => 'Actor',
                            'character_name' => $actor['character'] ?? null,
                            'credit_order' => $order
                        ]);
                    }
                }

                $insertadas++;
                Log::info("Película guardada en BD: {$film->title}");
            } catch (\Throwable $e) {
                $fallidas++;
                Log::error("Error procesando {$movie['title']}: " . $e->getMessage());
            }
        }
        // Sleep de seguridad entre páginas para no saturar las peticiones
        sleep(1); // espera 1 segundo entre páginas
        if ($page % 5 === 0) {
            Log::info("Pausa de seguridad tras página $page...");
            sleep(3); // cada 5 páginas, pausa más larga
        }
    }

    Log::info("Importación en BD finalizada. Insertadas: $insertadas | Fallidas: $fallidas");

    return response()->json([
        'message' => 'Importación finalizada',
        'insertadas' => $insertadas,
        'fallidas' => $fallidas
    ]);
}


//___ Funciones auxiliares ____

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
        OPTIONAL {
            { wd:$wikidataId wdt:P4232 ?festival. }
            UNION
            { wd:$wikidataId wdt:P1433 ?festival. }
            UNION
            { wd:$wikidataId wdt:P1191 ?festival. }
        }
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

    // Cargar caché local desde archivo JSON
    private function loadWikidataCache()
    {
        $path = storage_path('app/wikidata_cache.json');
        if (!file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);
        return json_decode($content, true) ?? [];
    }

    // Guardar caché local en archivo JSON
    private function saveWikidataCache(array $cache)
    {
        $path = storage_path('app/wikidata_cache.json');
        file_put_contents($path, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

}

