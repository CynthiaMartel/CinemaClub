<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

// CRUD: Manejo de los Posts (Noticias)
class PostController extends Controller
{
    
    // MOSTRAR todos los posts
    // - Si el usuario es admin o editor, puede ver todas las noticias
    // - Si es usuario "regular" o visitante, solo puede ver las que quedan marcadas como visibles al público
    
    public function index()
    {
        $user = Auth::user();

        // Cualquier visitante puede ver los posts visibles 
        // Si el usuario está logueado y es admin o editor, se muestran todos los posts
        if ($user && ($user->isAdmin() || $user->isEditor())) { 
        $posts = Post::orderBy('created_at', 'desc')->get();
        } 
         // En cualquier otro caso (en donde exista user regular o visitante externo no logueado), se muestra SOLO los visibles
        else {
            $posts = Post::where('visible', true)->orderBy('created_at', 'desc')->get();
        }

        return response()->json($posts);
    }

    // GUARDAR un nuevo post
    // - Si el usuario es admin o editor, puede guardar un post
    // - El usuario "regular" o visitante, queda restringido de esta función
     
    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user || !($user->isAdmin() || $user->isEditor())) {

            return response()->json(['message' => 'No tienes permisos para crear posts.'], 403);
        }

        $validated = $request->validate([ // Validar en array clave-valor los datos del post
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string|max:255',
            'content' => 'required|string',
            'img' => 'nullable|url|max:255',
            'visible' => 'boolean',
            'editorName' => 'nullable|string|max:150',
        ]);

        $validated['idUser'] = $user->id; // Validar en array clave-valor la autenticación del user

        $post = Post::create($validated);

        return response()->json([
            'message' => 'Post creado correctamente.',
            'post' => $post
        ], 201);
    }

    // MOSTRAR un solo post por ID
    // - Cualquier visitante puede ver las noticias visibles
    // - Solo Admin o Editor pueden ver las no visibles
     
    public function show($id)
    {
        $post = Post::findOrFail($id);
        $user = Auth::user();

        // Si el post no es visible y el usuario no es admin ni editor, se restringe el acceso prohibido
        if (!$post->visible && 
        (!$user || !($user->isAdmin() || $user->isEditor())) 
        ) {
            return response()->json(['message' => 'No tienes permiso para ver este post.'], 403);
        }

        // En cualquier otro caso, mostrar el post
        return response()->json($post);
    }

    // ACTUALIZAR un post existente
    // - Solo Admin o Editor pueden actualizar
     
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        if (!$user || !($user->isAdmin() || $user->isEditor)) {
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
    // - Solo Admin o Editor pueden eliminar
    
    public function destroy($id)
    {
        $user = Auth::user();

        if (!$user || !($user->isAdmin() || $user->isEditor)) {
            return response()->json(['message' => 'No tienes permisos para eliminar posts.'], 403);
        }

        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(['message' => 'Post eliminado correctamente.']);
    }
}

