<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    // MOSTRAR todos los posts  ***mejorar soporte búsqueda
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = $request->query('search'); // Capturamos la búsqueda 

        // Iniciamos la consulta
        $query = Post::query();

        // Si hay una búsqueda, filtramos por título, subtítulo o contenido
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('subtitle', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        // Ordenamos siempre de más reciente a más antiguo 
        $query->orderBy('created_at', 'desc');

        // Aplicamos roles
        if ($user && ($user->isAdmin() || $user->isEditor())) {
            $posts = $query->get();
        } else {
            // Solo visibles para users regulares
            $posts = $query->where('visible', true)->get();
        }

        return response()->json($posts);
    }

    // GUARDAR un nuevo post
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || !($user->isAdmin() || $user->isEditor())) {
            return response()->json(['message' => 'No tienes permisos para crear posts.'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'required|string',
            'img' => 'nullable|url|max:255',
            'visible' => 'boolean',
            'editorName' => 'nullable|string|max:150',
        ]);

        $validated['idUser'] = $user->id; 

        $post = Post::create($validated);

        return response()->json([
            'message' => 'Post creado correctamente.',
            'post' => $post
        ], 201);
    }

    // MOSTRAR un solo post por ID
    public function show($id)
    {
        $post = Post::findOrFail($id);
        $user = Auth::user();

        if (!$post->visible && (!$user || !($user->isAdmin() || $user->isEditor()))) {
            return response()->json(['message' => 'No tienes permiso para ver este post.'], 403);
        }

        return response()->json($post);
    }

    // ACTUALIZAR un post existente
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user || !($user->isAdmin() || $user->isEditor())) {
            return response()->json(['message' => 'No tienes permisos para actualizar posts.'], 403);
        }

        $post = Post::findOrFail($id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'required|string',
            'img' => 'nullable|url|max:255',
            'visible' => 'boolean',
            'editorName' => 'nullable|string|max:150',
        ]);

        $post->update($validated);

        return response()->json([
            'message' => 'Post actualizado correctamente.',
            'post' => $post
        ]);
    }

    // ELIMINAR un post
    public function destroy($id)
    {
        $user = Auth::user();

        if (!$user || !($user->isAdmin() || $user->isEditor())) {
            return response()->json(['message' => 'No tienes permisos para eliminar posts.'], 403);
        }

        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(['message' => 'Post eliminado correctamente.']);
    }
}