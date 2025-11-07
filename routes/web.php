<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;

use App\Http\Controllers\FilmController;
use App\Http\Controllers\FilmDataController;
use App\Http\Controllers\TestsDBApisController;

use App\Http\Controllers\PostController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\UserFilmActionController;


use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


/*
|--------------------------------------------------------------------------
|  --RUTAS CRUD FILMS--
|--------------------------------------------------------------------------
*/
// --RUTAS CRUD: FilmController CRUD llenado manual de datos de películas-- //
Route::get('/films', [FilmController::class, 'index'])->name('films.index'); // Obtener datos listado de películas
Route::get('/films/{film}', [FilmController::class, 'show'])->name('films.show'); // Obtener datos de una película concreta
Route::post('/films', [FilmController::class, 'store'])->name('films.store'); // Guardado de películas
Route::put('/films/{film}', [FilmController::class, 'update'])->name('films.update'); // Actualizar una película 
Route::delete('/films/{film}', [FilmController::class, 'destroy'])->name('films.destroy'); // Borrado de una película

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


/*
|--------------------------------------------------------------------------
|  --RUTAS AUTH--
|--------------------------------------------------------------------------
*/
// --Rutas de autentificación con login, logout, comprobación de sesión (en AuthController) y registro de creación de nueva cuenta (RegisterController) --// 

// LOGIN: inicia sesión y devuelve token
Route::post('/api/login', [AuthController::class, 'login'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->name('api.login');

// LOGOUT: cierra sesión (requiere token Sanctum)
Route::post('/api/logout', [AuthController::class, 'logout'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->middleware('auth:sanctum') //Activa guardia sanctum: con middleware comprueba que el token que se genera, corresponde a usuario autenticado 
    ->name('api.logout');        // mildware Necesario ya que para hacer logout, tiene que estar logueado primero y por lo tanto tener un token)
    

// CHECK SESSION: devuelve usuario autenticado si el token es válido
Route::get('/api/check-session', [AuthController::class, 'checkSession'])
    ->middleware('auth:sanctum') // Activa guardia sanctum: middleware para comprobar token, ya que hay sesión cuando el usuario está logueado 
    ->name('api.checkSession');  // No se pone withoutMiddleware porque peticiones tipo GET no necesitan token Bearer

// REGISTER: crea una nueva cuenta de usuario
Route::post('/api/register', [RegisterController::class, 'register'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->name('api.register');


/*
|--------------------------------------------------------------------------
|  -- RUTAS NOTICIAS (POSTS): PostController --
|--------------------------------------------------------------------------
*/

Route::get('/api/post-index', [PostController::class, 'index'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('api.post.index');

Route::post('/api/post-store', [PostController::class, 'store'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.post.store');

Route::get('/api/post-show/{id}', [PostController::class, 'show'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('api.post.show');

Route::put('/api/post-update/{id}', [PostController::class, 'update'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.post.update');

Route::delete('/api/post-destroy/{id}', [PostController::class, 'destroy'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.post.destroy');


/*
|--------------------------------------------------------------------------
|     -- RUTAS DE API USER PROFILE --
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->prefix('api')->group(function () {

    // MOSTRAR todos los perfiles -> Si es ADMIN 
    Route::get('/user_profiles/index', [UserProfileController::class, 'index'])
        ->name('api.user_profiles.index');

    // CREAR NUEVO PERFIL -> Si es ADMIN 
    Route::post('/user_profiles/store', [UserProfileController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_profiles.store');

    // MOSTRAR PERFIL POR ID DEL USER -> Si es ADMIN o USER REGULAR LOGEUADO 
    Route::get('/user_profiles/show/{userId?}', [UserProfileController::class, 'show'])
        ->name('api.user_profiles.show');

    // ACTUALIZAR PERFIL -> Si es ADMIN o USER LOGUEADO
    Route::put('/user_profiles/update/{userId}', [UserProfileController::class, 'update'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_profiles.update');

    // ELIMINAR PERFIL -> Si es ADMIN 
    Route::delete('/user_profiles/delete/{id}', [UserProfileController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_profiles.destroy');
});



/*
|--------------------------------------------------------------------------
|     -- RUTAS USER FILMS ACTIONS --
|--------------------------------------------------------------------------
*/
// Rutas para Crear o actualizar sección de favs, watch later, watched... ; eliminar alguna de estas; mostrar listas de favs, ratings, etc; mostrar estadísticas

    Route::middleware('auth:sanctum')->prefix('api')->group(function () {

    // CREAR/ACTUALIZAR una acción de usuario (favorito, ver más tarde, vista, puntuación, etc.)
    Route::post('/films/createOrEdit/{filmId}', [UserFilmActionController::class, 'storeOrUpdate'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.action.createOrEdit');

    // DESMARCAR una acción específica (quitar favorito, reseña, etc.)
    Route::delete('/films/unmarkAction/{filmId}', [UserFilmActionController::class, 'unmarkAction'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.action.unmarkAction');

    // MOSTRAR LISTAS de películas según tipo de acción (favorites, watch_later, watched, rating)
    Route::get('/my_films', [UserFilmActionController::class, 'showUserFilmCollection'])
        ->name('api.user.films.my_films');

    // MOSTRAR ESTADÍSTICAS de actividad del usuario (admin o logueado)
    Route::get('/user_films/stats/{userId?}', [UserFilmActionController::class, 'showStats'])
        ->name('api.user.films.stats');
});


