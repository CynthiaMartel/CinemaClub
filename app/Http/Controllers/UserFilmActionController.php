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
        $film = Film::find($filmId);

        if (!$film) {
            return response()->json(['error' => 'La película no existe.'], 404);
        }

        $validated = $request->validated();

        // Buscar o crear la acción del usuario sobre la película
        $action = UserFilmActions::firstOrNew([
            'idUser' => $user->id,
            'idFilm' => $filmId,
        ]);

        $action->fill($validated);
        $action->save();

        // Si el usuario puntua la película, actualizar promedio directamente desde user_film_actions
        if (isset($validated['rating'])) {
            $average = UserFilmActions::where('idFilm', $filmId)
                ->whereNotNull('rating')
                ->avg('rating');

            $film->individualRate = round($average, 2);
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
 
    public function showStats($userId = null): JsonResponse
    {
        $authUser = Auth::user();
        $userId = $userId ?? $authUser->id;

        // Solo admin puede ver otros usuarios
        if ($authUser->id !== $userId && !$authUser->isAdmin()) {
            return response()->json(['error' => 'No tienes permiso para ver estas estadísticas.'], 403);
        }

        $stats = [
            'favorites' => UserFilmActions::where('idUser', $userId)->where('is_favorite', true)->count(),
            'watch_later' => UserFilmActions::where('idUser', $userId)->where('watch_later', true)->count(),
            'watched' => UserFilmActions::where('idUser', $userId)->where('watched', true)->count(),
            'rated' => UserFilmActions::where('idUser', $userId)->whereNotNull('rating')->count(),
            'last_updated' => UserFilmActions::where('idUser', $userId)->max('updated_at'),
        ];

        return response()->json([
            'success' => true,
            'user_id' => $userId,
            'stats' => $stats,
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


