<?php

namespace App\Http\Controllers;

use App\Models\Film;
use App\Models\User;
use App\Models\UserEntry;
use App\Models\Post;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Búsqueda global: films, usuarios, entradas (debates/listas/reviews) y posts/noticias
     * GET /api/search?q=alien
     */
    public function global(Request $request)
    {
        $q = trim($request->get('q', ''));

        if (strlen($q) < 2) {
            return response()->json(['success' => true, 'data' => [
                'films'   => [],
                'users'   => [],
                'entries' => [],
                'posts'   => [],
            ]]);
        }

        // Films
        $films = Film::where('title', 'like', "%{$q}%")
            ->orWhere('original_title', 'like', "%{$q}%")
            ->orderBy('release_date', 'desc')
            ->limit(8)
            ->get()
            ->map(fn($f) => [
                'id'    => $f->idFilm,
                'type'  => 'film',
                'title' => $f->title,
                'year'  => $f->release_date ? substr($f->release_date, 0, 4) : null,
                'frame' => $f->frame,
                'genre' => $f->genre,
            ]);

        // Usuarios
        $users = User::where('name', 'like', "%{$q}%")
            ->with('profile:user_id,avatar')
            ->limit(6)
            ->get()
            ->map(fn($u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'type'   => 'user',
                'title'  => $u->name,
                'avatar' => $u->profile?->avatar,
            ]);

        // Entradas (listas, debates, reviews)
        $entries = UserEntry::where('title', 'like', "%{$q}%")
            ->with([
                'user:id,name',
                'films' => fn($q) => $q->select('films.idFilm', 'films.frame')->limit(1),
            ])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(fn($e) => [
                'id'    => $e->id,
                'type'  => $e->type,
                'title' => $e->title,
                'frame' => $e->films->first()?->frame,
                'user'  => $e->user?->name,
            ]);

        // Posts / Noticias
        $posts = Post::where('title', 'like', "%{$q}%")
            ->where('visible', 1)
            ->orderBy('created_at', 'desc')
            ->limit(6)
            ->get()
            ->map(fn($p) => [
                'id'    => $p->id,
                'type'  => 'post',
                'title' => $p->title,
                'frame' => $p->img,
                'user'  => $p->editorName,
            ]);

        return response()->json([
            'success' => true,
            'data' => [
                'films'   => $films,
                'users'   => $users,
                'entries' => $entries,
                'posts'   => $posts,
            ],
        ]);
    }
}
