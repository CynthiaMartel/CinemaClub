<?php

namespace App\Http\Controllers;

use App\Models\User;
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

        // Sanitizar el contenido HTML de CKEditor para prevenir XSS.
        // Permitimos únicamente etiquetas de formato seguras — cualquier
        // <script>, evento inline (onerror, onclick…) o iframe queda eliminado.
        if (!empty($validated['content'])) {
            $validated['content'] = strip_tags($validated['content'],
                '<p><br><strong><em><u><s><ul><ol><li><h2><h3><h4><blockquote><a><img><figure><figcaption>'
            );
        }

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
        $authUser = Auth::guard('sanctum')->user();

        $query = UserEntry::with(['user:id,name', 'films:idFilm,title,frame'])
            ->withCount('likes'); 

        if ($authUser) {
            $query->withExists(['likes as i_liked' => function($q) use ($authUser) {
                $q->where('user_id', $authUser->id);
            }]);
        }

        
        if ($request->query('sort') === 'popular') {
            // Primero los que tienen más likes, luego los más nuevos
            $query->orderBy('likes_count', 'desc')
                  ->orderBy('created_at', 'desc');
        } else {
            // Orden por defecto: Solo los más nuevos
            $query->orderBy('created_at', 'desc');
        }

        //filtro
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($userId = $request->query('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($filmId = $request->query('idFilm')) {
            $query->whereHas('films', fn($q) => $q->where('films.idFilm', $filmId));
        }

        if (!$authUser || !$authUser->isAdmin()) {
            $query->where('visibility', 'public')
                ->where('status', 'approved');
        }

        $entries = $query->paginate(15);

        return response()->json([
            'success' => true,
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

    // ACTUALIZAR una entrada existente (solo el dueño o admin)

    public function update(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $entry = UserEntry::findOrFail($id);

        if ($entry->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'No tienes permiso para editar esta entrada.'], 403);
        }

        $validated = $request->validate([
            'type'       => 'sometimes|in:user_list,user_debate,user_review',
            'title'      => 'sometimes|required|string|max:255',
            'content'    => 'nullable|string',
            'visibility' => 'sometimes|in:public,friends,private',
        ]);

        if (!empty($validated['content'])) {
            $validated['content'] = strip_tags($validated['content'],
                '<p><br><strong><em><u><s><ul><ol><li><h2><h3><h4><blockquote><a><img><figure><figcaption>'
            );
        }

        $entry->update($validated);

        if ($request->has('film_ids')) {
            $entry->films()->sync($request->film_ids);
        }

        return response()->json([
            'success' => true,
            'data' => $entry->load('films'),
        ], 200);
    }

    // ELIMINAR una entrada (solo el dueño o admin)

    public function destroy($id): JsonResponse
    {
        $user = Auth::user();
        $entry = UserEntry::findOrFail($id);

        if ($entry->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'No tienes permiso para eliminar esta entrada.'], 403);
        }

        $entry->delete();

        return response()->json([
            'success' => true,
            'message' => 'Entrada eliminada correctamente.',
        ], 200);
    }

    // MOSTRAR COLECCIÓN DE LISTS, DEBATES Y REVIEWS que el usuario haya creado

    public function getCreatedLists($username): JsonResponse
    {
        $targetUser = User::where('name', $username)->firstOrFail();
        $userId = $targetUser->id;

        $authUser = Auth::guard('sanctum')->user();
        $isOwner = $authUser && $authUser->id === $userId;
        $isAdmin = $authUser && $authUser->isAdmin();

        $query = UserEntry::where('user_id', $userId)
            ->where('type', 'user_list')
            ->where('status', 'approved')
            ->with(['films:idFilm,frame'])
            ->orderBy('created_at', 'desc');

        if (!$isOwner && !$isAdmin) {
            $query->where('visibility', 'public');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ], 200);
    }

    public function getCreatedDebates($username): JsonResponse
    {
        $targetUser = User::where('name', $username)->firstOrFail();
        $userId = $targetUser->id;

        $authUser = Auth::guard('sanctum')->user();
        $isOwner = $authUser && $authUser->id === $userId;
        $isAdmin = $authUser && $authUser->isAdmin();

        $query = UserEntry::where('user_id', $userId)
            ->where('type', 'user_debate')
            ->where('status', 'approved')
            ->with(['films:idFilm,frame'])
            ->orderBy('created_at', 'desc');

        if (!$isOwner && !$isAdmin) {
            $query->where('visibility', 'public');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ], 200);
    }

    public function getCreatedReviews($username): JsonResponse
    {
        $targetUser = User::where('name', $username)->firstOrFail();
        $userId = $targetUser->id;

        $authUser = Auth::guard('sanctum')->user();
        $isOwner = $authUser && $authUser->id === $userId;
        $isAdmin = $authUser && $authUser->isAdmin();

        $query = UserEntry::where('user_id', $userId)
            ->where('type', 'user_review')
            ->where('status', 'approved')
            ->with(['films:idFilm,frame'])
            ->orderBy('created_at', 'desc');

        if (!$isOwner && !$isAdmin) {
            $query->where('visibility', 'public');
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ], 200);
    }
  
}



