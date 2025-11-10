<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;

use App\Http\Controllers\FilmController;
use App\Http\Controllers\FilmDataController;
use App\Http\Controllers\TestsDBApisController;

use App\Http\Controllers\PostController;

use App\Http\Controllers\UserProfileController;

use App\Http\Controllers\UserEntryController;
use App\Http\Controllers\UserCommentController;
use App\Http\Controllers\UserSavedListController;
use App\Http\Controllers\UserEntryFilmController;


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

/*
|--------------------------------------------------------------------------
|     -- RUTAS USER ENTRIES --
|--------------------------------------------------------------------------
*/
// Rutas para entradas de listas, debates, reseñas

//UserEntryController: Para el control de entradas que crean los usuarios (listas, debates y reseñas)
Route::middleware('auth:sanctum')->prefix('api')->group(function () {

    //  MOSTRAR feed general de entradas (reviews, debates, listas)
    //  Permite filtrar por tipo de entrada(reviews, listas, debates), usuario o película asociada. 
    // Ejemplo: /api/user_entries/feed?type=user_review&user_name=Cynthia
    Route::get('/user_entries/feed', [UserEntryController::class, 'showEntries'])
        ->name('api.user_entries.feed');

    //  MOSTRAR una entrada concreta por ID de entrada
    //  Permite ver el detalle completo de una lista, debate o reseña (según visibilidad)
    Route::get('/user_entries/{id}', [UserEntryController::class, 'show'])
        ->name('api.user_entries.show');

    //  CREAR nueva lista, debate o reseña
    //  Solo usuarios autenticados pueden crear entradas
    Route::post('/user_entries/create', [UserEntryController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entries.create');

    //  ELIMINAR una entrada (solo para user o admin)
    Route::delete('/user_entries/{id}', [UserEntryController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entries.destroy');

    //  ME GUSTA : Dar o quitar “like” a una entrada
    //  Se actualiza automáticamente el contador likes_count
    Route::post('/user_entries/{entryId}/like', [UserEntryController::class, 'toggleLike'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entries.toggleLike');

    //  GUARDAR o quitar una lista (en user_saved_lists) SOLO TIPO USER_LISTS
    //  Permite a los usuarios guardar o eliminar listas y después lo mostamos en su perfil
    Route::post('/user_entries_lists/{entryId}/save', [UserEntryController::class, 'toggleSaveList'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entries.toggleSaveList');

    //  MOSTRAR las listas creadas por un usuario (para uso en su perfil personal)
    //  Muestra todas las listas creadas por un usuario concreto en su perfil personal
    Route::get('/user_profiles/{userId}/lists', [UserEntryController::class, 'showUserLists'])
        ->name('api.user_profiles.lists');


});

// ----USER LISTS---
// UserSavedListController: Listas que crean los usuarios (ej: "Top 5 películas de terror 2025") 

Route::middleware('auth:sanctum')->prefix('api')->group(function () {

    // GUARDAR O ELIMINAR listas (solo type = user_list)  
    Route::post('/user_entries/{entryId}/save', [UserSavedListController::class, 'toggle'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_saved_lists.toggle');

     // ASOCIAR entradas a películas
    Route::post('/user_entry_films/create', [UserEntryFilmController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entry_films.store');

    // MOSTRAR películas asociadas a una entrada con id de entrada
    Route::get('/user_entry_films/{entryId}/showByFilm', [UserEntryFilmController::class, 'showByFilm'])
        ->name('api.user_entry_films.show');

    // ELIMINAR la relación película-entrada con id de la asociación UserEntryFilm
    Route::delete('/user_entry_films/{id}/delete', [UserEntryFilmController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entry_films.destroy');


    // --USER COMMENTS --
    // UserCommentController: comentarios con polimorfismos para usar en films (comentarios de películas) o en entradas (comentarios en entradas de usuarios)
     
    // OBTENER comentarios (de un film o una entry) por IdFilm o o id de la entrada
    Route::get('/comments/{type}/{id}', [UserCommentController::class, 'index'])
        ->name('api.comments.index');

    // CREAR comentario (en film o entry) por IdFilm o o id de la entrada
    Route::post('/comments/{type}/{id}/create', [UserCommentController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.comments.store');

    // ELIMINAR comentario por ID
    Route::delete('/comments/{commentId}/delete', [UserCommentController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.comments.destroy');

});

