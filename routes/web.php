<?php

use App\Http\Controllers\FilmController;
use App\Http\Controllers\FilmDataController;
use App\Http\Controllers\TestsDBApisController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ChangePasswordController;

Route::get('/', function () {
    return view('welcome');
});

// --RUTAS CRUD: FilmController CRUD llenado manual de datos de películas-- //
Route::get('/films', [FilmController::class, 'index'])->name('films.index'); // Obtener datos listado de películas
Route::get('/films/{film}', [FilmController::class, 'show'])->name('films.show'); // Obtener datos de una película concreta
Route::post('/films', [FilmController::class, 'store'])->name('films.store'); // Guardado de películas
Route::put('/films/{film}', [FilmController::class, 'update'])->name('films.update'); // Actualizar una película 
Route::delete('/films/{film}', [FilmController::class, 'destroy'])->name('films.destroy'); // Borrado de una película
//----------------------------------------------------------------------------//

//-------- RUTAS TEMPORALES PARA PROBAR TRAER DATOS DESDE API TMDB Y API WIKI DATA EN POSTMAN ------------//

// Ruta para obtener el token CSRF desde Postman o cliente externo
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token(),]);});
    
// Test TMDB + wikidata pero solo lectura, sin guardar en BD
Route::get('/films/test_tmdb/{year}', [TestsDBApisController::class, 'testTMDb'])->name('films.test.tmdb');


// Test Wikidata solo lectura, solo premios/nominaciones/festivales 
Route::get('/films/test_wikidata/{wikidataId}', [TestsDBApisController::class, 'testWikidata'])->name('films.test.wikidata');

// Test WikiData solo lectura, para saber si busca por título
Route::get('/wikidata/test_title_wikidata/{title}', [TestsDBApisController::class, 'testFindWikidataIdByTitle']);

//----------------------------------------------------------------------------------------------------------------//

//Ruta para importar desde APIs wikidata y tmdb --> poblar y guardar en BD (si en la función se cambia la variable limit por un núnero reducido, puede servir de prueba rápida para ver si se puebla la BD correctamente)
Route::post('/films/import/{yearStart}/{yearEnd}/{startPage?}/{endPage?}', [FilmDataController::class, 'importFromTMDB'])->name('films.import');

// Ruta para manejar el Job por si usamos Postman o desde la Web
Route::post('/films/import/{start}/{end}/{from}/{to}', function($start,$end,$from,$to){'\App\Jobs\ImportFilmsJob'::dispatch((int)$start,(int)$end,(int)$from,(int)$to);
    return response()->json([
        'message'=>"Job encolado {$start}-{$end}, páginas {$from}-{$to}"
    ]);
});

//-------------------------------------------------------------------------------------------------------------------------//


// --RUTAS AUTH: rutas de autentificación con login, logout, comprobación de sesión (en AuthController) y registro de creación de nueva cuenta (RegisterController) --// 

// LOGIN: inicia sesión y devuelve token
Route::post('/api/login', [AuthController::class, 'login'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->name('api.login');

// LOGOUT: cierra sesión (requiere token Sanctum)
Route::post('/api/logout', [AuthController::class, 'logout'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->middleware('auth:sanctum') //Activa guardia sanctum: con middleware comprueba que el token que se genera, correspondiendo a usuario autenticado 
    ->name('api.logout');        // mildware Necesario ya que para hacer logout, tiene que estar logueado primero y por lo tanto tener un token)
    

// CHECK SESSION: devuelve usuario autenticado si el token es válido (Requiere token Sanctum)
Route::get('/api/check-session', [AuthController::class, 'checkSession'])
    ->middleware('auth:sanctum') // Activa guardia sanctum: middleware para comprobar token, ya que hay sesión cuando el usuario está logueado 
    ->name('api.checkSession');  // No se pone withoutMiddleware porque peticiones tipo GET no necesitan token Bearer

// ----------------------------------------------------------------------------------------- //

// --RUTA REGISTER: para crea una nueva cuenta de usuario (en RegisterController)-- //
Route::post('/api/register', [RegisterController::class, 'register'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->name('api.register');
// ----------------------------------------------------------------------------------------- //

// --RUTA CHANGE PASSWORD: para cambiar contraseña a otra nueva o (en ChangePasswordController) (requiere token Sanctum)--//
Route::post('/api/change-password', [ChangePasswordController::class, 'update']) 
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.changePassword');
