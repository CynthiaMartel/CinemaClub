<?php

namespace App\Http\Controllers;

use App\Models\UserFriend;
use App\Models\UserEntry;
use App\Models\UserFilmActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class UserFeedController extends Controller
{
    //Feed principal del user logueado (autenticado)
     // Muestra actividad de las personas que sigue, según visibilidad 
     
    public function index(Request $request)
    {
        $userId = Auth::id();

        // OBTENER los IDs FOLLOWERS: usuarios que user sigue (solo relaciones aceptadas, no bloqueadas)
        $followedIds = UserFriend::where('follower_id', $userId)
            ->where('status', 'accepted')
            ->pluck('followed_id');

        if ($followedIds->isEmpty()) {
            return response()->json(['feed' => []]);
        }

        // Para VER Actividad en user_entries (listas, debates, reseñas)
        $entries = UserEntry::whereIn('user_id', $followedIds)
            ->whereIn('visibility', ['public', 'friends'])
            ->where('status', 'approved')
            ->select('id', 'user_id', 'type', 'title', 'visibility', 'likes_count', 'created_at')
            ->with('user:id,name,email')
            ->latest('created_at');

        // Para VER Actividad en user_film_actions (películas vistas, puntuadas, favoritas)
        $films = UserFilmActions::whereIn('idUser', $followedIds)
            ->whereIn('visibility', ['public', 'friends',])
            ->select('id', 'idUser', 'idFilm', 'is_favorite', 'watched', 'watch_later', 'visibility' ,'rating', 'created_at')
            ->with(['user:id,name,email', 'film:idFilm,title,frame'])
            ->latest('created_at');

        // Para unificar resultados
        $feed = collect();

        // Normalizar tipos de actividad en formato uniforme
        foreach ($entries->get() as $entry) {
            $feed->push([
                'type' => $entry->type,
                'source' => 'entry',
                'user' => $entry->user->name,
                'user_id' => $entry->user_id,
                'title' => $entry->title,
                'likes_count' => $entry->likes_count,
                'created_at' => $entry->created_at,
                'visibility' => $entry->visibility,
            ]);
        }

        foreach ($films->get() as $action) {
            $filmTitle = $action->film->title ?? 'Película desconocida';
            $feed->push([
                'type' => 'film_action',
                'source' => 'film',
                'user' => $action->user->name,
                'user_id' => $action->idUser,
                'film_id' => $action->idFilm,
                'film_title' => $filmTitle,
                'rating' => $action->rating,
                'watched' => $action->watched,
                'is_favorite' => $action->is_favorite,
                'created_at' => $action->created_at,
                'visibility' => $action->visibility,
            ]);
        }

        // Para ordenar feed por fecha (más reciente primero)
        $feed = $feed->sortByDesc('created_at')->values();

        return response()->json(['feed' => $feed]);
    }
}

