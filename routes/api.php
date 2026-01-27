<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ChangePasswordController;

use App\Http\Controllers\FilmController;
use App\Http\Controllers\FilmDataController;
use App\Http\Controllers\TestsDBApisController;

use App\Http\Controllers\PostController;

use App\Http\Controllers\UserProfileController;

use App\Http\Controllers\UserEntryController;
use App\Http\Controllers\UserCommentController;
use App\Http\Controllers\UserSavedListController;
use App\Http\Controllers\UserEntryFilmController;
use App\Http\Controllers\UserFriendsController;
use App\Http\Controllers\UserFilmActionController;

use App\Http\Controllers\UserFeedController;
use App\Http\Controllers\UserController;

use Illuminate\Support\Facades\Route;

use Illuminate\Http\Request;


// web.php -> contiene rutas "públicas" (consumibles por usuarios y rutas consumidas para el fronted)


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/*
|--------------------------------------------------------------------------
|  --RUTAS DE FILMS--
|--------------------------------------------------------------------------
*/

// BÚSQUEDA barra de búsqueda -> PÚBLICAS
// Ejemplo: GET http://cinemaclub.test/api/films/search?q=alien
Route::get('/films/search', [FilmController::class, 'search'])
    ->name('api.films.search');

// VER película concreta 
// Ejemplo: GET http://cinemaclub.test/api/films/3 (por id)
Route::get('/films/{film}', [FilmController::class, 'show'])
    ->name('api.films.show');

// para ADMIN -> REQUIEREN AUTENTIFICACIÓN
Route::middleware('auth:sanctum') 
->group(function () {
    // CREAR película (para llenado manual)
    Route::post('/films/store', [FilmController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.store');

    // ACTUALIZAR película (de forma manual)
    Route::put('/films/{film}/update', [FilmController::class, 'update'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.update');

    // BORRAR película
    Route::delete('/films/{film}/delete', [FilmController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.destroy');
});


/*
|--------------------------------------------------------------------------
|  --RUTAS AUTH--
|--------------------------------------------------------------------------
*/
// --Rutas de autentificación con login, logout, comprobación de sesión (en AuthController) y registro de creación de nueva cuenta (RegisterController) --// 

// LOGIN: inicia sesión y devuelve token
Route::post('/login', [AuthController::class, 'login'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->name('api.login');

// LOGOUT: cierra sesión (requiere token Sanctum)
Route::post('/logout', [AuthController::class, 'logout'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->middleware('auth:sanctum') //Activa guardia sanctum: con middleware comprueba que el token que se genera, corresponde a usuario autenticado 
    ->name('api.logout');        // mildware Necesario ya que para hacer logout, tiene que estar logueado primero y por lo tanto tener un token)
    

// CHECK SESSION: devuelve usuario autenticado si el token es válido
Route::get('/check-session', [AuthController::class, 'checkSession'])
    ->middleware('auth:sanctum') // Activa guardia sanctum: middleware para comprobar token, ya que hay sesión cuando el usuario está logueado 
    ->name('api.checkSession');  // No se pone withoutMiddleware porque peticiones tipo GET no necesitan CSRF

// REGISTER: crea una nueva cuenta de usuario
Route::post('/register', [RegisterController::class, 'register'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class) // Quita guardia de CSRF propio de formularios web: acepta peticiones API (JSON, sin cookies,) al trabajar con api
    ->name('api.register');


/*
|--------------------------------------------------------------------------
|  --RUTAS DE CHANGE PASSWORD--
|--------------------------------------------------------------------------
*/
// Ruta para cambio de contraseña
Route::post('/change-password', [ChangePasswordController::class, 'update'])
    ->middleware('auth:sanctum')
    ->name('api.changePassword');

/*
|--------------------------------------------------------------------------
|  -- RUTAS NOTICIAS (POSTS): PostController --
|--------------------------------------------------------------------------
*/

Route::get('/post-index', [PostController::class, 'index'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('api.post.index');

Route::post('/post-store', [PostController::class, 'store'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.post.store');

Route::get('/post-show/{id}', [PostController::class, 'show'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->name('api.post.show');

Route::put('/post-update/{id}', [PostController::class, 'update'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.post.update');

Route::delete('/post-destroy/{id}', [PostController::class, 'destroy'])
    ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
    ->middleware('auth:sanctum')
    ->name('api.post.destroy');


/*
|--------------------------------------------------------------------------
|     -- RUTAS DE API USER PROFILE --
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

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

    Route::middleware('auth:sanctum')->group(function () {

    // CREAR/ACTUALIZAR una acción de usuario (favorito, ver más tarde, vista, puntuación, etc.)
    Route::post('/films/createOrEdit/{film_id}', [UserFilmActionController::class, 'storeOrUpdate'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.action.createOrEdit');

    // VER UNA ACCIÓN EN ESPECÍFICO: rated, watched, en watch_list...
    Route::get('/films/show-user-action/{film_id}', [UserFilmActionController::class, 'showAction']);

    // DESMARCAR una acción específica (quitar favorito, reseña, etc.)
    Route::delete('/films/unmarkAction/{film_id}', [UserFilmActionController::class, 'unmarkAction'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.films.action.unmarkAction');

    // MOSTRAR LISTAS de películas según tipo de acción (favorites, watch_later, watched, rating)
    Route::get('/my_films_diary/{user_id?}', [UserFilmActionController::class, 'showUserFilmDiary'])
        ->name('api.user.films.my_films_diary');

    // MOSTRAR ESTADÍSTICAS de actividad del usuario (admin o logueado)
    Route::get('/user_films/stats/{user_id?}', [UserFilmActionController::class, 'showStats'])
        ->name('api.user.films.stats');
});

/*
|--------------------------------------------------------------------------
|     -- RUTAS CAST_CREW --
|--------------------------------------------------------------------------
*/
// Ruta para mostrar actores y actrices, director con cada film asociado

use App\Http\Controllers\CastCrewController;

Route::get('{id}/cast-crew', [CastCrewController::class, 'show']);

/*
|--------------------------------------------------------------------------
|     -- RUTAS USER ENTRIES --
|--------------------------------------------------------------------------
*/
// Rutas para entradas de listas, debates, reseñas

//UserEntryController: Para el control de entradas que crean los usuarios (listas, debates y reseñas)

    //  MOSTRAR feed general de entradas (reviews, debates, listas): es para usuarios logueados y no logueados
    //  Permite filtrar por tipo de entrada(reviews, listas, debates), usuario o película asociada. 
    // Ejemplo: /api/user_entries/feed?type=user_review&user_name=Cynthia
    Route::get('/user_entries/feed', [UserEntryController::class, 'showEntriesFeed'])
        ->name('api.user_entries.feed');

    //  MOSTRAR UNA entrada concreta por ID de entrada
    //  Permite ver el detalle completo de una lista, debate o reseña (según visibilidad)
    Route::get('/user_entries/{id}/show', [UserEntryController::class, 'show']);
   


Route::middleware('auth:sanctum')->group(function () {
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
    Route::post('/user_entries_lists/{entryId}/save', [UserSavedListController::class, 'toggleSaveList'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entries.toggleSaveList');


    //  MOSTRAR las listas guardadas del usuario USERSAVEDLISTCONTROLLER
    Route::get('/user_profiles/{userId}/saved-lists', [UserSavedListController::class, 'getSavedLists']);

    // MOSTRAR COLECCIÓN DE LISTAS, DEBATES, REVIEWS CREADAS por el usuario
    Route::prefix('user_profiles/{userId}')->group(function () {
    Route::get('/lists', [UserEntryController::class, 'getCreatedLists']);
    Route::get('/debates', [UserEntryController::class, 'getCreatedDebates']);
    Route::get('/reviews', [UserEntryController::class, 'getCreatedReviews']);

    });

});

// ----ASIGNACIÓN DE FILMS A ENTRIES (DEBATE, REVIEW,LIST)---
// UserSavedListController: Listas que crean los usuarios (ej: "Top 5 películas de terror 2025") 

Route::middleware('auth:sanctum')->group(function () {

    // GUARDAR O ELIMINAR listas (solo type = user_list)  : *ESTÁ YA EN UNAS LÍNEAS MÁS ARRIBA ASÍ QUE LO COMENTO PARA NO SATURAR CÓDDIGO*
    /* Route::post('/user_entries_lists/{entryId}/save', [UserSavedListController::class, 'toggleSaveList'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_entries.toggleSaveList'); */

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

    // CREAR comentario (en film o entry) por IdFilm o o id de la entrada
    Route::post('/comments/{type}/{id}/create', [UserCommentController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.comments.store');

    // ELIMINAR comentario por ID
    Route::delete('/comments/{commentId}/delete', [UserCommentController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.comments.destroy');

});
    // OBTENER comentarios (de un film o una entry) por IdFilm o o id de la entrada
    Route::get('/comments/{type}/{id}', [UserCommentController::class, 'index'])
        ->name('api.comments.index');


/*
|--------------------------------------------------------------------------
|     -- RUTAS USER FRIENDS --
|--------------------------------------------------------------------------
*/
// Rutas para relaciones de amigos del user en UserFriendsController (flistas followers, followings, seguir, bloquear...)
Route::middleware('auth:sanctum')->group(function () {

    // SEGUIR a un usuario
    Route::post('/user_friends/{id}/follow', [UserFriendsController::class, 'follow'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_friends.follow');

    //DEJAR DE SEGUIR usuario
    Route::delete('/user_friends/{id}/unfollow', [UserFriendsController::class, 'unfollow'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_friends.unfollow');

    // BLOQUEAR a un usuario
    Route::post('/user_friends/{id}/block', [UserFriendsController::class, 'block'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_friends.block');

    // DESBLOQUEAR a un usuario
    Route::delete('/user_friends/{id}/unblock', [UserFriendsController::class, 'unblock'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.user_friends.unblock');

    // VER lista de followers
    Route::get('/user_friends/followers/{id?}', [UserFriendsController::class, 'followers'])
        ->name('api.user_friends.followers');

    // VER LISTA de followings
    Route::get('/user_friends/followings/{id?}', [UserFriendsController::class, 'followings'])
        ->name('api.user_friends.followings');

    // VER LISTA de bloqueados
    Route::get('/user_friends/blocked', [UserFriendsController::class, 'blocked'])
        ->name('api.user_friends.blocked');
});


/*
|--------------------------------------------------------------------------
|     -- RUTA FEED FRIENDS --
|--------------------------------------------------------------------------
*/
// Rutas para actividades de las relaciones de amistad del user (listas followers, followings, lista bloqueados)
Route::middleware('auth:sanctum')->group(function () {

    // VER FEED (actividad) de amigos que user sigue
    Route::get('/feed', [UserFeedController::class, 'index'])
        ->name('api.user_feed.index');
});

/*
|--------------------------------------------------------------------------
|     -- RUTAS USERS (ADMIN / GESTIÓN CUENTAS) --
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // LISTAR usuarios (con filtros ?q=, ?role=, ?blocked=)
    Route::get('/users', [UserController::class, 'index'])
        ->name('api.users.index');

    // BÚSQUEDA rápida para barra de búsqueda
    Route::get('/users/search', [UserController::class, 'search'])
        ->name('api.users.search');

    // VER detalle de un usuario
    Route::get('/users/{user}', [UserController::class, 'show'])
        ->name('api.users.show');

    // CREAR usuario (para ADMIN)
    Route::post('/users/create', [UserController::class, 'store'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.users.store');

    // ACTUALIZAR usuario (para ADMIN o el propio user)
    Route::put('/users/{user}/update', [UserController::class, 'update'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.users.update');

    // ELIMINAR usuario (para ADMIN o el propio user)
    Route::delete('/users/{user}/destroy', [UserController::class, 'destroy'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.users.destroy');

    // BLOQUEAR usuario (para ADMIN)
    Route::post('/users/{user}/block', [UserController::class, 'block'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.users.block');

    // DESBLOQUEAR usuario (para ADMIN)
    Route::post('/users/{user}/unblock', [UserController::class, 'unblock'])
        ->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class)
        ->name('api.users.unblock');
});

