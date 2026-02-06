<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserFilmActionsRequest;
use App\Models\User; 
use App\Models\UserFilmActions;
use App\Models\Film;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserFilmActionController extends Controller
{

    //Crear o actualizar si usuario marca como watched, puntúa
    public function storeOrUpdate(UserFilmActionsRequest $request, $filmId): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $action = UserFilmActions::firstOrNew(['user_id' => $user->id, 'film_id' => $filmId]);

        // Lógica de Watched_at (Diario)
        $isNewWatch = isset($validated['watched']) && $validated['watched'] == true;
        $isNewRating = isset($validated['rating']) && $validated['rating'] > 0;

        if (($isNewWatch || $isNewRating) && !$action->watched_at) {
            $action->watched_at = now(); 
        }
        
        $action->fill($validated);
        $action->save(); 

        if (isset($validated['rating'])) {
            $this->recalculateFilmRating($filmId);
        }

        return response()->json([
            'success' => true,
            'message' => 'Acción actualizada.',
            'data' => $action,
        ], 200);
    }
    
    // MOSTRAR DIARIO / WATCHLIST / FAVORITOS
    public function showUserFilmDiary(Request $request, $user_id = null): JsonResponse
    {
        $targetId = $user_id ?? Auth::id();
        $type = $request->query('type');
        $perPage = $request->query('per_page', 20); 

        $validTypes = [
            'favorites'   => 'is_favorite',
            'watch_later' => 'watch_later',
            'watched'     => 'watched',
            'rating'      => 'rating',
            'diary'       => 'diary'
        ];

        if (!array_key_exists($type, $validTypes)) {
            return response()->json(['error' => 'Tipo no válido.'], 400);
        }

        $query = UserFilmActions::select('user_film_actions.*')
            ->join('films', 'user_film_actions.film_id', '=', 'films.idFilm') 
            ->with('film')
            ->where('user_film_actions.user_id', $targetId);

        if ($type === 'diary') {
            $query->where(function($q) {
                $q->where('user_film_actions.watched', true)
                ->orWhereNotNull('user_film_actions.rating');
            });
            $orderByField = 'user_film_actions.watched_at'; 
            
        } elseif ($type === 'rating') {
            $query->whereNotNull('user_film_actions.rating');
            $orderByField = 'user_film_actions.watched_at';
            
        } else {
            // Favoritos / Watchlist
            $column = $validTypes[$type];
            $query->where('user_film_actions.' . $column, true);
            $orderByField = 'user_film_actions.updated_at';
        }

        $query->orderBy($orderByField, 'desc')
            ->orderBy('films.release_date', 'desc');

        $paginated = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'type' => $type,
            'pagination' => [
                'total' => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
            ],
            'data' => $paginated->items(),
        ], 200);
    }

  //Mostrar tendencia en homeview para films watched o puntuadas más recientemente
    public function getTrendingFilms(Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);

        // obtenemos los IDs de las películas con interacción reciente
        // Usamos una subconsulta para que el GROUP BY no rompa los campos del SELECT
        $trendingIdsQuery = UserFilmActions::select('film_id')
            ->selectRaw('MAX(updated_at) as last_act')
            ->where(function($q) {
                $q->where('watched', true)
                  ->orWhereNotNull('rating');
            })
            ->groupBy('film_id');

        // traemos todas las películas haciendo un LEFT JOIN con esa subconsulta
        // Esto garantiza que si no hay actividad, el orden por release_datese priorice
        $films = Film::select('films.*', 'sub.last_act')
            ->leftJoinSub($trendingIdsQuery, 'sub', function ($join) {
                $join->on('films.idFilm', '=', 'sub.film_id');
            })
            // ORDEN 1: Actividad más reciente
            ->orderByRaw('sub.last_act DESC')
            // ORDEN 2: Fecha de estreno (desempate)
            ->orderBy('films.release_date', 'desc')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $films->items(),
            'pagination' => [
                'has_more' => $films->hasMorePages()
            ]
        ], 200);
    }
    // DESMARCAR ACCIÓN
    public function unmarkAction(Request $request, $filmId): JsonResponse
    {
        $user = Auth::user();
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

        if (in_array($field, ['is_favorite', 'watch_later', 'watched'])) {
            $action->$field = false;
        } else {
            $action->$field = null;
        }

        $action->save();
        $action->touch();

        if ($field === 'rating') {
            $this->recalculateFilmRating($filmId);
        }

        return response()->json([
            'success' => true,
            'message' => "Acción '{$field}' eliminada correctamente.",
            'data' => $action
        ], 200);
    }
    
    // MOSTRAR ESTADÍSTICAS
    public function showStats($userId = null) 
    {
        $targetId = $userId ?? Auth::id();

        if (!$targetId) {
            return response()->json(['error' => 'Usuario no encontrado.'], 404);
        }

        $userStats = User::where('id', $targetId)
            ->withCount([
                'filmActions as films_total_count' => function ($query) {
                    $query->where(function ($q) {
                        $q->where('watched', true)->orWhereNotNull('rating');
                    });
                },
                'filmActions as films_rated_count' => function ($query) {
                    $query->whereNotNull('rating');
                },
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

    // MOSTRAR UNA ACCIÓN
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

    // AUXILIAR
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