<?php

namespace App\Http\Controllers;

use App\Models\UserComment;
use App\Models\UserEntry;
use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class UserCommentController extends Controller
{
    // MOSTRAR comentarios de un film o una entry por IdFilm o o id de la entrada
     
    public function index(string $type, int $id): JsonResponse
    {
        $modelClass = $this->resolveModel($type);

        if (!$modelClass) {
            return response()->json(['error' => 'Tipo de entidad no válido.'], 400);
        }

        $comments = UserComment::where('commentable_type', $modelClass)
            ->where('commentable_id', $id)
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'total' => $comments->count(),
            'data' => $comments,
        ], 200);
    }

    //CREAR nuevo comentario en un film o una entry por IdFilm o o id de la entrada
     
    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $user = Auth::user();
        $modelClass = $this->resolveModel($type);

        if (!$modelClass) {
            return response()->json(['error' => 'Tipo de entidad no válido.'], 400);
        }

        $request->validate([
            'comment' => 'required|string|max:1000',
            'visibility' => 'in:public,friends,private',
        ]);

        $comment = UserComment::create([
            'user_id' => $user->id,
            'commentable_id' => $id,
            'commentable_type' => $modelClass,
            'comment' => $request->comment,
            'visibility' => $request->visibility ?? 'public',
        ]);

        $comment->load('user:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Comentario publicado correctamente.',
            'data' => $comment,
        ], 201);
    }

    // ELIMINAR comentario por ID del comentario (usuario logueado o admin)
    
    public function destroy(int $commentId): JsonResponse
    {
        $user = Auth::user();
        $comment = UserComment::findOrFail($commentId);

        if ($comment->user_id !== $user->id && !$user->isAdmin()) {
            return response()->json(['error' => 'No tienes permiso para eliminar este comentario.'], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comentario eliminado correctamente.',
        ], 200);
    }


    // Para modelo polimórfico según el tipo recibido en los otros métodos anteriores (film o entry)
    
    private function resolveModel(string $type): ?string
    {
        return match ($type) {
            'film' => Film::class,
            'entry' => UserEntry::class,
            default => null,
        };
    }
}



