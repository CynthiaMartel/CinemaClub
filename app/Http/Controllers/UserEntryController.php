<?php

namespace App\Http\Controllers;

use App\Models\UserEntry;
use App\Models\UserEntryLike;
use App\Models\UserProfile;
use App\Models\UserSavedList;
use App\Http\Requests\StoreUserEntryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserEntryController extends Controller
{
    // CREAR nueva entrada : lista, debate o reseña

    public function store(StoreUserEntryRequest $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validated();
        $validated['user_id'] = $user->id;

        // Crear  entrada lista, debate o reseña
        $entry = UserEntry::create($validated);

        // SI HAY films desde array film_ids que enviamos desde fronted EntryFormView ****
        if ($request->has('film_ids')) {
            // Este método sync() guarda automáticamente en la tabla 'user_entry_films' asociando el ID den entry con los IDs de films
            $entry->films()->sync($request->film_ids);
        }

        return response()->json([
            'success' => true,
            'data' => $entry->load('films'), // Devolvemos la entrada con sus películas
        ], 201);
    }

    // MOSTRAR una entrada específica (por ID) No hace falta estar logueado para ver entradas PÚBLICAS, si son privadas sí!
    
    public function show($id): JsonResponse
    {
        // Auth::user() devolverá null si el usuario no está logueado
        $authUser = Auth::user(); 
        
        $entry = UserEntry::with(['user:id,name', 'films:idFilm,title,frame', 'comments.user:id,name'])
            ->findOrFail($id);

        // Vemos la PRIVACIDAD y si es privado no se podrá ver si no está logueado
        if ($entry->visibility === 'private') {
            // Si NO hay usuario, o hay usuario pero NO es el dueño y NO es admin
            if (!$authUser || ($entry->user_id !== $authUser->id && !$authUser->isAdmin())) {
                return response()->json(['error' => 'No tienes permiso para ver esta entrada.'], 403);
            }
        }

        // Vemos ESTADO (Moderación) **Esto es para la escalabilidad: si hay entradas con spam, contenido inadecuado, borradores.. con esto se evitaría que se publicase sin ser aprobado antes
        if ($entry->status !== 'approved') {
            // Si NO hay usuario, o (hay usuario pero NO es el dueño y NO es admin)
            if (!$authUser || ($entry->user_id !== $authUser->id && !$authUser->isAdmin())) {
                return response()->json(['error' => 'La entrada aún no está disponible públicamente.'], 403);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $entry,
        ], 200);
    }

    // MOSTRAR feed filtrado de entradas (reviews, debates, listas)

    public function showEntries(Request $request): JsonResponse
    {
        $authUser = Auth::user();

        $query = UserEntry::with(['user:id,name', 'films:idFilm,title'])
            ->orderBy('created_at', 'desc');

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        } elseif ($userName = $request->query('user_name')) {
            $query->whereHas('user', fn($q) => $q->where('name', 'LIKE', "%$userName%"));
        }

        if ($filmId = $request->query('idFilm')) {
            $query->whereHas('films', fn($q) => $q->where('films.idFilm', $filmId));
        }

          // Para filtrar por título de la entrada
        if ($title = $request->query('title')) {
            $query->where('title', 'LIKE', "%$title%");
        }

        if (!$authUser->isAdmin()) {
            $query->where('visibility', 'public')
                  ->where('status', 'approved');
        }

        $entries = $query->paginate(10);

        return response()->json([
            'success' => true,
            'filters' => $request->all(),
            'total' => $entries->total(),
            'data' => $entries,
        ], 200);
    }

    // DAR O QUITAR "LIKES" (me gusta) a una entrada
     
    public function toggleLike($entryId): JsonResponse
    {
        $user = Auth::user();
        $entry = UserEntry::findOrFail($entryId);

        $like = UserEntryLike::where('user_entry_id', $entryId)
            ->where('user_id', $user->id)
            ->first();

        if ($like) {
            $like->delete();
            $entry->decrement('likes_count');
            $message = 'Like eliminado.';
        } else {
            UserEntryLike::create([
                'user_entry_id' => $entryId,
                'user_id' => $user->id,
            ]);
            $entry->increment('likes_count');
            $message = 'Like añadido.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'likes_count' => $entry->likes_count,
        ], 200);
    }


    // MOSTRAR las listas creadas por un usuario (para perfil en fronted)
    
    public function showUserLists($userId): JsonResponse
    {
        $lists = UserEntry::where('user_id', $userId)
            ->where('type', 'user_list')
            ->with('films:idFilm,title')
            ->get();

        return response()->json([
            'success' => true,
            'total' => $lists->count(),
            'data' => $lists,
        ], 200);
    }
}



