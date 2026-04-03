<?php

namespace App\Http\Controllers;

use App\Models\UserFriend;
use App\Models\UserEntry;
use App\Models\UserFilmActions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

class UserFeedController extends Controller
{
    //Feed principal del user logueado (autenticado)
     // Muestra actividad de las personas que sigue, según visibilidad 
     
    public function index(Request $request)
    {
        $userId = Auth::id();

        // Feed personal: cacheamos 5 minutos por usuario
        // Si el usuario sigue a alguien que acaba de publicar algo, lo verá
        // en un máximo de 5 minutos — balance razonable entre frescura y rendimiento
        $feed = Cache::remember("feed_user_{$userId}", now()->addMinutes(5), function () use ($userId) {

            $followedIds = UserFriend::where('follower_id', $userId)
                ->where('status', 'accepted')
                ->pluck('followed_id');

            if ($followedIds->isEmpty()) {
                return [];
            }

            $entries = UserEntry::whereIn('user_id', $followedIds)
                ->whereIn('visibility', ['public', 'friends'])
                ->where('status', 'approved')
                ->select('id', 'user_id', 'type', 'title', 'visibility', 'likes_count', 'created_at')
                ->with('user:id,name,email')
                ->latest('created_at')
                ->get();

            $films = UserFilmActions::whereIn('user_id', $followedIds)
                ->whereIn('visibility', ['public', 'friends'])
                ->select('id', 'user_id', 'film_id', 'is_favorite', 'watched', 'watch_later', 'visibility', 'rating', 'updated_at', 'created_at')
                ->with(['user.profile', 'film:idFilm,title,frame'])
                ->latest('updated_at')
                ->get();

            $feed = collect();

            foreach ($entries as $entry) {
                $feed->push([
                    'type'        => $entry->type,
                    'source'      => 'entry',
                    'user'        => $entry->user->name,
                    'user_id'     => $entry->user_id,
                    'title'       => $entry->title,
                    'likes_count' => $entry->likes_count,
                    'created_at'  => $entry->created_at,
                    'visibility'  => $entry->visibility,
                ]);
            }

            foreach ($films as $action) {
                $feed->push([
                    'type'        => 'film_action',
                    'source'      => 'film',
                    'user'        => $action->user->name ?? 'Usuario',
                    'user_id'     => $action->user_id,
                    'user_avatar' => $action->user->avatar ?? $action->user->profile->avatar ?? null,
                    'film_id'     => $action->film_id,
                    'film_title'  => $action->film->title ?? 'Película desconocida',
                    'film_frame'  => $action->film->frame ?? null,
                    'rating'      => $action->rating,
                    'watched'     => $action->watched,
                    'is_favorite' => $action->is_favorite,
                    'updated_at'  => $action->updated_at,
                    'visibility'  => $action->visibility,
                ]);
            }

            return $feed->sortByDesc('updated_at')->values()->all();
        });

        return response()->json(['feed' => $feed]);
    }
}
