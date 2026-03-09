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
        $films = UserFilmActions::whereIn('user_id', $followedIds)
            ->whereIn('visibility', ['public', 'friends'])
            ->select('id', 'user_id', 'film_id', 'is_favorite', 'watched', 'watch_later', 'visibility' ,'rating', 'updated_at', 'created_at')
            ->with(['user.profile', 'film:idFilm,title,frame']) // Cargar profile para avatar
            ->latest('updated_at'); 

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
            
            // Buscar el avatar (puede estar directo en user o dentro de profile)
            $avatar = $action->user->avatar ?? $action->user->profile->avatar ?? null;

            $feed->push([
                'type' => 'film_action', 
                'source' => 'film',
                'user' => $action->user->name ?? 'Usuario',
                'user_id' => $action->user_id,
                'user_avatar' => $avatar, 
                'film_id' => $action->film_id,
                'film_title' => $filmTitle,
                'film_frame' => $action->film->frame ?? null,   
                'rating' => $action->rating,
                'watched' => $action->watched,
                'is_favorite' => $action->is_favorite,
                'updated_at' => $action->updated_at,
                'visibility' => $action->visibility,
            ]);
        }

        // Ordenar por updated_at descendente
        $feed = $feed->sortByDesc('updated_at')->values();

        return response()->json(['feed' => $feed]);
    }
}
