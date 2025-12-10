<?php

use App\Http\Controllers\FilmDataController;
use App\Http\Controllers\TestsDBApisController;

use Illuminate\Support\Facades\Route;


// web.php -> contiene rutas de mantenimiento y utilidades de desarrollo, como rutas de funcionamiento "interno", no de uso "público" para usuarios de la app


Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
|  --RUTAS DE PRUEBA CONEXIÓN APIS Y LLENADO DE BD--
|--------------------------------------------------------------------------
*/
// --Rutas para probar traer datos desde API TMDB y API WIKIDATA vía Postman o similar, y comprobar la conexión a estas APIS-- //

// Ruta para obtener el token CSRF desde Postman o cliente externo
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token(),]);});
    
// Test TMDB + wikidata pero solo lectura, sin guardar en BD
Route::get('/films/test_tmdb/{year}', [TestsDBApisController::class, 'testTMDb'])->name('films.test.tmdb');


// Test Wikidata solo lectura, solo premios/nominaciones/festivales 
Route::get('/films/test_wikidata/{wikidataId}', [TestsDBApisController::class, 'testWikidata'])->name('films.test.wikidata');

// Test WikiData solo lectura, para saber si busca por título
Route::get('/wikidata/test_title_wikidata/{title}', [TestsDBApisController::class, 'testFindWikidataIdByTitle']);


/*
|--------------------------------------------------------------------------
|  --RUTAS IMPORTACIÓN DATOS A BD--
|--------------------------------------------------------------------------
*/
// --Rutas para importar desde APIs wikidata y tmdb --> poblar y guardar en BD (si en la función se cambia la variable limit por un núnero reducido, puede servir de prueba rápida para ver si se puebla la BD correctamente)
Route::post('/films/import/{yearStart}/{yearEnd}/{startPage?}/{endPage?}', [FilmDataController::class, 'importFromTMDB'])->name('films.import');

// Ruta para manejar el Job por si usamos Postman o desde la Web
Route::post('/films/import/{start}/{end}/{from}/{to}', function($start,$end,$from,$to){'\App\Jobs\ImportFilmsJob'::dispatch((int)$start,(int)$end,(int)$from,(int)$to);
    return response()->json([
        'message'=>"Job encolado {$start}-{$end}, páginas {$from}-{$to}"
    ]);
});



