<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserProfileRequest;
use App\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use App\Models\UserFriend;

class UserProfileController extends Controller
{
    
    // Listar todos los perfiles (para solo ADMIN)
     
    public function index(): JsonResponse
    {
        /** @var User $user */
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
        /** @var User $user */
        $user = Auth::user();

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Acceso denegado.'], 403);
        }

        $validated = $request->validated();

        // Convertir el string JSON a Array PHP
        if (isset($validated['top_films'])) {
            $validated['top_films'] = json_decode($validated['top_films'], true);
        }

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
        // INTENTAMOS OBTENER EL USUARIO DESDE EL TOKEN
        // Usamos el guard sanctum
        $authUser = Auth::guard('sanctum')->user(); 

        // Debug para ver en el log quién es quién (opcional, bórralo luego)
        \Illuminate\Support\Facades\Log::info('Perfil Show Debug:', [
             'Quien_mira_ID' => $authUser ? $authUser->id : 'INVITADO',
             'Perfil_que_se_ve_ID' => $userId
        ]);

        // 2. Resolver el ID del perfil que queremos ver
        if (!$userId) {
            if (!$authUser) {
                return response()->json(['error' => 'Usuario no especificado.'], 404);
            }
            $userId = $authUser->id;
        }

        // LÓGICA DE BLOQUEO (Hard Block)
        // -------------------------------------------------------
        if ($authUser && $authUser->id != $userId) {
            
            // CASO ¿yo bloquee a este perfil?
            $iBlockedHim = UserFriend::where('follower_id', $authUser->id)
                ->where('followed_id', $userId)
                ->where('status', 'blocked')
                ->exists();

            if ($iBlockedHim) {
                return response()->json(['message' => 'Has bloqueado a este usuario. Desbloquéalo para ver su perfil.'], 403);
            }

            // CASO ¿ÉL/ella me bloqueó a m´í?
            // Aquí comprobamos si EL (userId) me tiene bloqueado a MI (authUser->id)
            $heBlockedMe = UserFriend::where('follower_id', $userId)
                ->where('followed_id', $authUser->id)
                ->where('status', 'blocked')
                ->exists();

            if ($heBlockedMe) {
                return response()->json(['message' => 'Este perfil no está disponible.'], 403);
            }
        }
        // ----------------------------------------------------

        // Buscar el perfil (solo llegamos aquí si NO hay bloqueo)
        $profile = UserProfile::with('user')->where('user_id', $userId)->first();

        if (!$profile) {
            return response()->json(['error' => 'No se encontró el perfil de este usuario.'], 404);
        }

        // lógica de permisos y seguimiento
        $isOwner = $authUser && $authUser->id === $profile->user_id;
        $isAdmin = $authUser && $authUser->isAdmin();
        $isFollowing = false;

        if ($authUser && !$isOwner) {
            $isFollowing = UserFriend::where('follower_id', $authUser->id)
                ->where('followed_id', $profile->user_id)
                ->where('status', 'accepted')
                ->exists();
        }

        return response()->json([
            'success' => true,
            'data' => $profile,
            'meta' => [
                'can_edit' => $isOwner || $isAdmin,
                'is_following' => $isFollowing
            ]
        ], 200);
    }



        // Actualizar perfil (si ADMIN o USER LOGUEADO) por ID de usuario
     
    public function update(UserProfileRequest $request, $userId): JsonResponse
    {
        $profile = UserProfile::where('user_id', $userId)->first();

        if (!$profile) {
            return response()->json(['error' => 'No se encontró el perfil del usuario.'], 404);
        }

        /** @var User $authUser */
        $authUser = Auth::user();

        if (!$authUser->isAdmin() && $authUser->id !== $profile->user_id) {
            return response()->json(['error' => 'No puedes modificar este perfil.'], 403);
        }

        $validated = $request->validated();

        // Convertir el string JSON a Array PHP ya que lo enviamos en el request como string para la base de datos
        if (isset($validated['top_films'])) {
            $validated['top_films'] = json_decode($validated['top_films'], true);
        }

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
        /** @var User $authUser */
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



