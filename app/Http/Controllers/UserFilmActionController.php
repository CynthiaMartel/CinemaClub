<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserFilmActionsRequest;
use App\Models\UserFilmActions;
use App\Models\IndividualRate;
use App\Models\Film;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use Carbon\Carbon;

class UserFilmActionController extends Controller
{
 

    // CREAR O ACTUALIZAR una acción de usuario sobre una película: marcar como favorita, ver después, vista, puntuar, escribir reseña, marcar visibilidad de la actividad

    public function storeOrUpdate(UserFilmActionsRequest $request, $filmId): JsonResponse
    {
        $user = Auth::user();
        $film = Film::findOrFail($filmId);

        $validated = $request->validated();

        // Guardar o Actualizar la nota del usuario
        $action = UserFilmActions::updateOrCreate(
            ['idUser' => $user->id, 'idFilm' => $filmId],
            $validated
        );

        // i ha enviado una nota, recalculamos la media de TODO EL CLUB
        $newAverage = 0;
        if (isset($validated['rating'])) {
            $newAverage = UserFilmActions::where('idFilm', $filmId)
                ->whereNotNull('rating')
                ->avg('rating');

            // Guardamos la media en globalRate
            $film->globalRate = round($newAverage, 1);
            $film->save();
        }

        // Para actualizar la estadísticas del perfil del usuario
        $this->updateUserStats($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Acción actualizada correctamente.',
            'data' => $action,
        ], 200);
    }


   // DESMARCAR acción específica (ej: quitar de favoritos, borrar puntuación, etc.)
 
    public function unmarkAction(Request $request, $filmId): JsonResponse
    {
        $user = Auth::user();

        $action = UserFilmActions::where('idUser', $user->id)
                                ->where('idFilm', $filmId)
                                ->first();

        if (!$action) {
            return response()->json(['error' => 'No existe registro para esta película.'], 404);
        }

        $field = $request->input('field'); // campo que el usuario quiere desmarcar

        $allowed = ['is_favorite', 'watch_later', 'watched', 'rating', 'short_review'];

        if (!in_array($field, $allowed)) {
            return response()->json(['error' => 'Campo no válido para eliminar.'], 400);
        }

        // Poner el campo a su valor “vacío”
        if (in_array($field, ['is_favorite', 'watch_later', 'watched'])) {
            $action->$field = false;
        } else {
            $action->$field = null;
        }

        $action->save();

        // Actualizar estadísticas del perfil
        $this->updateUserStats($user->id);

        return response()->json([
            'success' => true,
            'message' => "Acción '{$field}' eliminada correctamente.",
            'data' => $action
        ], 200);
    }


   // MOSTRAR LISTAS de películas según tipo de acción: favorites, watch_later, watched (user logueado)
    public function showUserFilmCollection(Request $request): JsonResponse
    {
        $user = Auth::user();
        $type = $request->query('type'); // ej: ?type=favorites

        $validTypes = [
            'favorites' => 'is_favorite',
            'watch_later' => 'watch_later',
            'watched' => 'watched',
            'rating' => 'films_rated',

        ];

        // Validar el tipo de acción solicitado
        if (!array_key_exists($type, $validTypes)) {
            return response()->json([
                'error' => 'Tipo de acción no válido. Usa: favorites, watch_later o watched.'
            ], 400);
        }

        // Obtener el campo correspondiente según el tipo
        $column = $validTypes[$type];

        // Consultar las películas según la acción
        $films = UserFilmActions::with('film')
            ->where('idUser', $user->id)
            ->where($column, true)
            ->get();

        return response()->json([
            'success' => true,
            'type' => $type,
            'total' => $films->count(),
            'data' => $films,
        ], 200);
    }

    // MOSTRAR ESTADÍSTICAS de actividad del usuario (admin o logueado)
 
    public function showStats($userId = null)
    {
        $targetId = $userId ?? auth()->id();

        //Consultar el perfil y contar las acciones (watched, rating, y vitas este año, )relacionadas
        $userStats = User::withCount([
            'filmActions as films_seen_count' => function ($query) {
                $query->where('watched', true);
            },
            'filmActions as films_rated_count' => function ($query) {
                $query->whereNotNull('rating');
            },
            'filmActions as seen_this_year_count' => function ($query) {
                $query->where('watched', true)
                    ->whereYear('updated_at', now()->year);
            }
        ])
        ->with('profile') // Traemos también el perfil con la bio, avatar, etc.
        ->findOrFail($targetId);

        // Devolver la respuesta
        return response()->json([
            'user' => [
                'name' => $userStats->name,
                'profile' => $userStats->profile,
                'stats' => [
                    'films_seen' => $userStats->films_seen_count,
                    'films_rated'   => $userStats->films_rated_count,
                    'films_seen_this_year' => $userStats->seen_this_year_count,
                    // Añadir 'Listas' aquí contando la relación de listas!!!!!! ***
                ]
            ]
        ]);
    }

    //Obtener una campo específico de las acciones del usuario : nota de film, si es fav, watched, puesta en la lista de watched..
    // UserFilmActionController.php

    public function showAction($filmId): JsonResponse
    {
        $user = Auth::user();
        
        // Buscamos el registro para ese usuario y esa película
        $action = UserFilmActions::where('idUser', $user->id)
                                ->where('idFilm', $filmId)
                                ->first();

        // Si no existe, devolvemos un objeto vacío o valores por defecto
        if (!$action) {
            return response()->json([
                'success' => true,
                'data' => [
                    'rating' => 0,
                    'is_favorite' => false,
                    'watched' => false,
                    'watch_later' => false
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => $action,
        ], 200);
    }

    // Método auxiliar:
    // Para actualizar estadísticas en el perfil del usuario (favoritas, vistas, vistas este año) que se utiliza en storeOrUpdate()
     
    private function updateUserStats(int $userId): void
    {
        $profile = UserProfile::where('user_id', $userId)->first();
        if (!$profile) return;

        $now = Carbon::now();

        $profile->films_seen = UserFilmActions::where('idUser', $userId)
            ->where('watched', true)
            ->count();

        $profile->films_rated = UserFilmActions::where('idUser', $userId)
            ->whereNotNull('rating')
            ->count();

        $profile->films_seen_this_year = UserFilmActions::where('idUser', $userId)
            ->where('watched', true)
            ->whereYear('updated_at', $now->year)
            ->count();

        $profile->save();
    }
}


