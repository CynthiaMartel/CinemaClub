<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserFilmActionsRequest;
use App\Models\User; 
use App\Models\UserFilmActions;
use App\Models\Film;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class UserFilmActionController extends Controller
{

    // CREAR O ACTUALIZAR una acción de usuario sobre una película: marcar como favorita, ver después, vista, puntuar, marcar visibilidad de la actividad
    public function storeOrUpdate(UserFilmActionsRequest $request, $filmId): JsonResponse
    {
        $user = Auth::user();
        $film = Film::findOrFail($filmId);
        $validated = $request->validated();

        // Guardar o Actualizar la acción del usuario usando user_id y film_id
        $action = UserFilmActions::updateOrCreate(
            ['user_id' => $user->id, 'film_id' => $filmId],
            $validated
        );

        // FORZAMOS la actualización de 'updated_at' para que las estadísticas de "este año" 
        // se actualicen aunque solo se haya cambiado la nota o el valor sea el mismo.
        $action->touch();

        // Si ha enviado una nota (rating), recalculamos la media de TODO EL CLUB para esa película
        if (isset($validated['rating'])) {
            $this->recalculateFilmRating($filmId);
        }

        // --- NOTA: No se llama a updateUserStats porque no existen esos campos en tu tabla profile ---

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

        // Buscamos el registro con user_id y film_id
        $action = UserFilmActions::where('user_id', $user->id)
                                ->where('film_id', $filmId)
                                ->first();

        if (!$action) {
            return response()->json(['error' => 'No existe registro para esta película.'], 404);
        }

        $field = $request->input('field'); 
        $allowed = ['is_favorite', 'watch_later', 'watched', 'rating', 'short_review'];

        if (!in_array($field, $allowed)) {
            return response()->json(['error' => 'Campo no válido para eliminar.'], 400);
        }

        // Resetear el campo según su tipo
        if (in_array($field, ['is_favorite', 'watch_later', 'watched'])) {
            $action->$field = false;
        } else {
            $action->$field = null;
        }

        $action->save();
        
        // Al desmarcar también actualizamos el timestamp para que las estadísticas sean coherentes
        $action->touch();

        // Si el usuario elimina su nota, debemos recalcular la media global de la película
        if ($field === 'rating') {
            $this->recalculateFilmRating($filmId);
        }

        return response()->json([
            'success' => true,
            'message' => "Acción '{$field}' eliminada correctamente.",
            'data' => $action
        ], 200);
    }


    // MOSTRAR COLECCIONES de películas (favorites, watch_later, watched)
    public function showUserFilmDiary(Request $request): JsonResponse
    {
        $user = Auth::user();
        $type = $request->query('type');

        $validTypes = [
            'favorites'   => 'is_favorite',
            'watch_later' => 'watch_later',
            'watched'     => 'watched',
            'rating'      => 'rating',
        ];

        if (!array_key_exists($type, $validTypes)) {
            return response()->json(['error' => 'Tipo no válido.'], 400);
        }

        $column = $validTypes[$type];

        $query = UserFilmActions::with('film')->where('user_id', $user->id);

        if ($type === 'rating') {
            $films = $query->whereNotNull($column)->get();
        } else {
            $films = $query->where($column, true)->get();
        }

        return response()->json([
            'success' => true,
            'type' => $type,
            'total' => $films->count(),
            'data' => $films,
        ], 200);
    }

    // MOSTRAR ESTADÍSTICAS para el perfil (Cálculo dinámico sin columnas extra en DB)
    public function showStats($userId = null) 
    {
        $targetId = $userId ?? auth()->id();

        $userStats = User::where('id', $targetId)
            ->withCount([
                // Total histórico: películas vistas O puntuadas
                'filmActions as films_total_count' => function ($query) {
                    $query->where(function ($q) {
                        $q->where('watched', true)->orWhereNotNull('rating');
                    });
                },
                // Total puntuadas
                'filmActions as films_rated_count' => function ($query) {
                    $query->whereNotNull('rating');
                },
                // Actividad de este año (2026): Cualquier interacción (vista o puntuada) este año
                'filmActions as watched_this_year_count' => function ($query) {
                    $query->where(function ($q) {
                        $q->where('watched', true)->orWhereNotNull('rating');
                    })->whereYear('updated_at', now()->year);
                },
            ])
            ->with('profile')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'user' => [
                'name' => $userStats->name,
                'profile' => $userStats->profile,
                'stats' => [
                    'films_seen'           => $userStats->films_total_count,
                    'films_rated'          => $userStats->films_rated_count,
                    'films_seen_this_year' => $userStats->watched_this_year_count,
                ]
            ]
        ]);
    }

    // Mostrar el estado de una película para el usuario actual
    public function showAction($filmId): JsonResponse
    {
        $user = Auth::user();
        $action = UserFilmActions::where('user_id', $user->id)
                                ->where('film_id', $filmId)
                                ->first();

        if (!$action) {
            return response()->json([
                'success' => true,
                'data' => ['rating' => 0, 'is_favorite' => false, 'watched' => false, 'watch_later' => false]
            ], 200);
        }

        return response()->json(['success' => true, 'data' => $action], 200);
    }

    // --- MÉTODOS AUXILIARES ---

    /**
     * Recalcula la nota media de la película y la guarda en la tabla films (globalRate).
     */
    private function recalculateFilmRating($filmId): void
    {
        $newAverage = UserFilmActions::where('film_id', $filmId)
            ->whereNotNull('rating')
            ->avg('rating');

        $film = Film::find($filmId);
        if ($film) {
            $film->globalRate = round($newAverage ?? 0, 1);
            $film->save();
        }
    }
}