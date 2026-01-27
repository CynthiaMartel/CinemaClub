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

    // MOSTRAR UNA entrada ESPECÍFICA (por ID) No hace falta estar logueado para ver entradas PÚBLICAS, si son privadas sí!
    
    public function show($id): JsonResponse
    {
        // Auth::guard() devolverá null si el usuario no está logueado
        $authUser = Auth::guard('sanctum')->user();
        
        $entry = UserEntry::with(['user:id,name', 'films:idFilm,title,frame,backdrop', 'comments.user:id,name'])
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

        // Comprobar si el usuario ya guardó esta lista'
        $entry->saved = false; 

        if ($authUser && $entry->type === 'user_list') {
            $entry->saved = UserSavedList::where('user_id', $authUser->id)
                ->where('user_entry_id', $id)
                ->exists(); // Devolvemos true o false
        }

        $entry->is_like = false; 
        if ($authUser) {
            $entry->is_like = UserEntryLike::where('user_id', $authUser->id)
                ->where('user_entry_id', $id)
                ->exists(); // Devolvemos true o false
        }

        // Comprobar si el usuario ya le dio like
        return response()->json([
            'success' => true,
            'data' => array_merge($entry->toArray(), ['saved' => $entry->saved, 'like'=> $entry->is_like]), 
        ]   , 200);
    }

    // MOSTRAR FEED FILTRADO de entradas (reviews, debates, listas)

    public function showEntriesFeed(Request $request): JsonResponse
    {
        // 1. Usamos Auth::guard('sanctum')->user() para que funcione con invitados y logueados
        $authUser = Auth::guard('sanctum')->user();

        // 2. Iniciamos la consulta con el conteo de likes
        $query = UserEntry::with(['user:id,name', 'films:idFilm,title,frame'])
            ->withCount('likes'); // Esto añade el campo 'likes_count' automáticamente

        if ($authUser) {
            $query->withExists(['likes as i_liked' => function($q) use ($authUser) {
                $q->where('user_id', $authUser->id);
            }]); // Para devolver boolean si el usuario ha dado me gusta a esa entry para mostrarlo en el feed
        }

        // 3. Lógica de Ordenación (Popularidad vs Recientes)
        if ($request->query('sort') === 'popular') {
            $query->orderBy('likes_count', 'desc'); // Ordenar por más likes
        } else {
            $query->orderBy('created_at', 'desc'); // Por defecto: más recientes
        }

        // --- Filtros existentes ---
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($filmId = $request->query('idFilm')) {
            $query->whereHas('films', fn($q) => $q->where('films.idFilm', $filmId));
        }

        // 4. SEGURIDAD CORREGIDA (Evitar error 500 si $authUser es null)
        if (!$authUser || !$authUser->isAdmin()) {
            $query->where('visibility', 'public')
                ->where('status', 'approved');
        }

        // 5. PAGINACIÓN: Definimos 15 por página
        $entries = $query->paginate(15);

        return response()->json([
            'success' => true,
            'data' => $entries, // Laravel ya incluye aquí total, current_page, etc.
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

    // MOSTRAR COLECCIÓN DE LISTS, DEBATES Y REVIEWS que el usuario haya creado

    public function getCreatedLists($userId): JsonResponse
    {
        $lists = UserEntry::where('user_id', $userId)
            ->where('type', 'user_list')
            ->where('status', 'approved') // Opcional: solo las aprobadas si es para perfil público
            ->with(['films:idFilm,frame']) 
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $lists
        ], 200);
    }

    public function getCreatedDebates($userId): JsonResponse
    {
        $debates = UserEntry::where('user_id', $userId)
            ->where('type', 'user_debate')
            ->where('status', 'approved')
            ->with(['films:idFilm,frame']) // Por si el debate tiene pelis asociadas
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $debates
        ], 200);
    }

    public function getCreatedReviews($userId): JsonResponse
    {
        $reviews = UserEntry::where('user_id', $userId)
            ->where('type', 'user_review')
            ->where('status', 'approved')
            ->with(['films:idFilm,frame']) 
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reviews
        ], 200);
    }
  
}



