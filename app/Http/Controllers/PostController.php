<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PostController extends Controller
{
    // MOSTRAR todos los posts
    public function index(Request $request)
    {
        // Forzamos la detección del usuario vía Sanctum***
        $user = auth('sanctum')->user();

        $search = $request->input('search'); 
        $query = Post::query();

        // Filtros de búsqueda
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'LIKE', "%{$search}%")
                  ->orWhere('subtitle', 'LIKE', "%{$search}%")
                  ->orWhere('content', 'LIKE', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        // LÓGICA DE VISIBILIDAD
        $canSeeEverything = false;

        if ($user) {
            // Verificamos permisos (Admin = 1, Editor = 2) o métodos del modelo
           
            if ($user->idRol == 1 || $user->idRol == 2 || (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isEditor()))) {
                $canSeeEverything = true;
            }
        }

        // Si NO tiene permiso total, solo mostramos los visibles::
        if (!$canSeeEverything) {
            $query->where('visible', 1);
        }

        $posts = $query->get();

        return response()->json($posts);
    }

    // GUARDAR un nuevo post
    public function store(Request $request)
    {
        //***
        $user = auth('sanctum')->user();

        // Comprobación de seguridad manual **Tengo que quitar los || y ver qué funciona
        if (!$user || !($user->idRol == 1 || $user->idRol == 2 || (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isEditor())))) {
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

    // MOSTRAR un solo post por ID  **Mirar paginación para limitar carga !! Por hacer**
    public function show($id)
    {
        $post = Post::findOrFail($id);
        
        // CORRECCIÓN: Usamos auth('sanctum')->user() para detectar el token en rutas públicas**
        $user = auth('sanctum')->user();

        // Lógica de permisos unificada
        $isAdminOrEditor = $user && ($user->idRol == 1 || $user->idRol == 2 || (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isEditor())));

        // Si el post NO es visible Y el usuario NO es admin/editor : Bloquear
        if (!$post->visible && !$isAdminOrEditor) {
            return response()->json(['message' => 'No tienes permiso para ver este post.'], 403);
        }

        return response()->json($post);
    }

    // ACTUALIZAR un post existente
    public function update(Request $request, $id)
    {
        $user = auth('sanctum')->user();

        // Comprobación de seguridad
        if (!$user || !($user->idRol == 1 || $user->idRol == 2 || (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isEditor())))) {
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
        $user = auth('sanctum')->user();

        if (!$user || !($user->idRol == 1 || $user->idRol == 2 || (method_exists($user, 'isAdmin') && ($user->isAdmin() || $user->isEditor())))) {
            return response()->json(['message' => 'No tienes permisos para eliminar posts.'], 403);
        }

        $post = Post::findOrFail($id);
        $post->delete();

        return response()->json(['message' => 'Post eliminado correctamente.']);
    }
}