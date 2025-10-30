<?php

use App\Http\Controllers\FilmController;
use App\Http\Controllers\FilmDataController;
use App\Http\Controllers\TestsDBApisController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// FilmController CRUD llenado manual de datos de películas
Route::get('/films', [FilmController::class, 'index'])->name('films.index'); // Obtener datos listado de películas
Route::get('/films/{film}', [FilmController::class, 'show'])->name('films.show'); // Obtener datos de una película concreta
Route::post('/films', [FilmController::class, 'store'])->name('films.store'); // Guardado de películas
Route::put('/films/{film}', [FilmController::class, 'update'])->name('films.update'); // Actualizar una película 
Route::delete('/films/{film}', [FilmController::class, 'destroy'])->name('films.destroy'); // Borrado de una película


//-------- RUTAS TEMPORALES PARA PROBAR TRAER DATOS DESDE API TMDB Y API WIKI DATA EN POSTMAN ------------

// Ruta para obtener el token CSRF desde Postman o cliente externo
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token(),]);});
    
// Test TMDB + wikidata pero solo lectura, sin guardar en BD
Route::get('/films/test_tmdb/{year}', [TestsDBApisController::class, 'testTMDb'])->name('films.test.tmdb');


// Test Wikidata solo lectura, solo premios/nominaciones/festivales 
Route::get('/films/test_wikidata/{wikidataId}', [TestsDBApisController::class, 'testWikidata'])->name('films.test.wikidata');

// Test WikiData solo lectura, para saber si busca por título
Route::get('/wikidata/test_title_wikidata/{title}', [TestsDBApisController::class, 'testFindWikidataIdByTitle']);

//----------------------------------------------------------------------------------------------------------------

//Routa para importar desde APIs wikidata y tmdb --> poblar y guardar en BD (si en la función se cambia limit por un núnero reducido, puede servir de prueba rápida para ver si se puebla la BD correctamente)
Route::post('/films/import/{yearStart}/{yearEnd}/{pages?}', [FilmDataController::class, 'importFromTMDb'])->name('films.import');


Route::post('/import/tmdb/async', [FilmDataController::class, 'importFromTMDBAsync']); // Para invocar un botón (ej en fronted y así importar las películas)


