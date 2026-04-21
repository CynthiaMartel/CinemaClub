<?php

namespace App\Http\Controllers;

use App\Http\Requests\UserProfileRequest;
use App\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
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

        // Subir avatar a Cloudinary si se adjunta
        if ($request->hasFile('avatar')) {
            $result = Cloudinary::uploadApi()->upload($request->file('avatar')->getRealPath(), [
                'folder'         => 'cinemaclub/avatars',
                'transformation' => [['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face']],
            ]);
            $validated['avatar'] = $result['secure_url'];
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
     
   public function show($username = null): JsonResponse
    {
        $authUser = Auth::guard('sanctum')->user();

        // Resolver el userId a partir del username o del token
        if (!$username) {
            if (!$authUser) {
                return response()->json(['error' => 'Usuario no especificado.'], 404);
            }
            $userId = $authUser->id;
        } else {
            $targetUser = User::where('name', $username)->first();
            if (!$targetUser) {
                return response()->json(['error' => 'Usuario no encontrado.'], 404);
            }
            $userId = $targetUser->id;
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
            // Borrar imagen anterior de Cloudinary si existe
            if ($profile->avatar && str_starts_with($profile->avatar, 'http')) {
                $publicId = pathinfo(parse_url($profile->avatar, PHP_URL_PATH), PATHINFO_FILENAME);
                Cloudinary::uploadApi()->destroy("cinemaclub/avatars/{$publicId}");
            }
            $result = Cloudinary::uploadApi()->upload($request->file('avatar')->getRealPath(), [
                'folder'         => 'cinemaclub/avatars',
                'transformation' => [['width' => 400, 'height' => 400, 'crop' => 'fill', 'gravity' => 'face']],
            ]);
            $validated['avatar'] = $result['secure_url'];
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

        // Si tiene avatar en Cloudinary, eliminarlo
        if ($profile->avatar && str_starts_with($profile->avatar, 'http')) {
            $publicId = pathinfo(parse_url($profile->avatar, PHP_URL_PATH), PATHINFO_FILENAME);
            Cloudinary::uploadApi()->destroy("cinemaclub/avatars/{$publicId}");
        }

        $profile->delete();

        return response()->json([
            'success' => true,
            'message' => 'Perfil eliminado correctamente.'
        ], 200);
    }

}



