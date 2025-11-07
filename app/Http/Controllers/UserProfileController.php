<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserProfileRequest;
use App\Models\UserProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;

class UserProfileController extends Controller
{
    
   // Listar todos los perfiles (para solo ADMIN)
     
    public function index(): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Acceso denegado.'], 403);
        }

        $profiles = UserProfile::with('user')->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $profiles
        ], 200);
    }

    // Crear un nuevo perfil por id del user (para solo ADMIN)
    
    public function store(UserProfileRequest $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validated();

        // Guardar avatar si se sube
        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $profile = UserProfile::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil creado correctamente.',
            'data' => $profile
        ], 201);
    }

    //  Mostrar un perfil por id del user
     // - Si eres admin: puedes ver cualquier perfil pasando user_id
     // - Si eres el propio usuario logueado: puedes ver tu propio perfil
     
    public function show($userId = null): JsonResponse
    {
        $authUser = Auth::user();

        // Si no se pasa ID, se muestra el perfil del propio usuario ya logueado
        if (!$userId) {
            $userId = $authUser->id;
        }

        // Para buscar el perfil asociado al usuario
        $profile = UserProfile::with('user')->where('user_id', $userId)->first();

        if (!$profile) {
            return response()->json(['error' => 'No se encontró el perfil de este usuario.'], 404);
        }

        // Permitir acceso si es ADMIN o el propio user
        if (!$authUser->isAdmin() && $authUser->id !== $profile->user_id) {
            return response()->json(['error' => 'No tienes permiso para ver este perfil.'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $profile
        ], 200);
    }



        // Actualizar perfil (si ADMIN o USER LOGUEADO) por ID de usuario
     
    public function update(UserProfileRequest $request, $userId): JsonResponse
    {
        // Buscar el perfil asociado al usuario
        $profile = UserProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return response()->json(['error' => 'No se encontró el perfil del usuario.'], 404);
        }

        $authUser = Auth::user();

        // Verificar permisos: solo admin o  usuario logueado
        if (!$authUser->isAdmin() && $authUser->id !== $profile->user_id) {
            return response()->json(['error' => 'No puedes modificar este perfil.'], 403);
        }

        $validated = $request->validated();

        // Si hay nueva imagen, borrar la anterior y guardar la nueva
        if ($request->hasFile('avatar')) {
            if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
                Storage::disk('public')->delete($profile->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        $profile->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Perfil actualizado correctamente.',
            'data' => $profile
        ], 200);
    }


        //  Eliminar perfil (para solo ADMIN) por ID de usuario
    public function destroy($userId): JsonResponse
    {
        $authUser = Auth::user();

        // limitación a que solo administradores pueden eliminar perfiles
        if (!$authUser->isAdmin()) {
            return response()->json(['error' => 'Acceso denegado.'], 403);
        }

        // Buscar el perfil por user_id
        $profile = UserProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return response()->json(['error' => 'No se encontró el perfil de este usuario.'], 404);
        }

        // Si tiene avatar, eliminarlo del almacenamiento
        if ($profile->avatar && Storage::disk('public')->exists($profile->avatar)) {
            Storage::disk('public')->delete($profile->avatar);
        }

        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente.'
        ], 200);
    }


}




