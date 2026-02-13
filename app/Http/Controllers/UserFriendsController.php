<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserFriend;
use App\Models\UserProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserFriendsController extends Controller
{
    // Para SEGUIR a un user con FOLLOW
    public function follow($followed_id)
    {
        $follower_id = Auth::id(); // Yo, el que intenta seguir

        // 1. Evitar seguirse a uno mismo
        if ($follower_id == $followed_id) {
            return response()->json(['error' => 'No puedes seguirte a ti mismo.'], 400);
        }

        // VALIDACIÓN: ¿Me bloqueó este usuario?
        // 
        // Buscamos si EL OTRO USUARIO ($followed_id) creó un registro de bloqueo contra MÍ ($follower_id
        $imBlocked = UserFriend::where('follower_id', $followed_id) // El que bloqueó 
            ->where('followed_id', $follower_id) // El bloqueado
            ->where('status', 'blocked')
            ->exists();

        if ($imBlocked) {
            return response()->json(['message' => 'Este usuario te ha bloqueado. No puedes seguirle.'], 403);
        }

        // VALIDACIÓN EXTRA: ¿Le he bloqueé yo?
        // -----------------------------
        $iBlockedHim = UserFriend::where('follower_id', $follower_id)
            ->where('followed_id', $followed_id)
            ->where('status', 'blocked')
            ->exists();

        if ($iBlockedHim) {
            return response()->json(['message' => 'Tú has bloqueado a este usuario. Debes desbloquearlo para seguirle.'], 403);
        }


        // Lógica normal de Seguir
        // -----------
        
        // Verificamos si ya existía la relación
        $existing = UserFriend::where('follower_id', $follower_id)
            ->where('followed_id', $followed_id)
            ->first();

        // Si ya existe y es 'accepted', no hacemos nada
        if ($existing && $existing->status === 'accepted') {
            return response()->json(['message' => 'Ya sigues a este usuario.'], 200);
        }

        // Crear o Reactivar la relación
        UserFriend::updateOrCreate(
            ['follower_id' => $follower_id, 'followed_id' => $followed_id],
            ['status' => 'accepted']
        );

        // Actualizar contadores
        $this->updateFollowerCounts($follower_id, $followed_id);

        return response()->json(['message' => 'Ahora sigues a este usuario.']);
    }

    // DEJAR DE SEGUIR de seguir a un usuario con UNFOLLOW 
    
    public function unfollow($followed_id)
    {
        $follower_id = Auth::id();

        $deleted = UserFriend::where('follower_id', $follower_id)
            ->where('followed_id', $followed_id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'No estabas siguiendo a este usuario.'], 404);
        }

        $this->updateFollowerCounts($follower_id, $followed_id);

        return response()->json(['message' => 'Has dejado de seguir a este usuario.']);
    }

    //BLOQUEAR a un usuario para evitar interacciones : si el user bloquea a otro, se marca el status como blocked y se elimina de los seguidores del user
    
    public function block($blocked_id)
    {
        $blocker_id = Auth::id();

        if ($blocker_id == $blocked_id) { // Evitamos bloquearnos a nosotros mismos
            return response()->json(['error' => 'No puedes bloquearte a ti.'], 400);
        }

        // Para crear o actualizar la relación como bloqueada
        UserFriend::updateOrCreate(
            ['follower_id' => $blocker_id, 'followed_id' => $blocked_id],
            ['status' => 'blocked']
        );

        // Eliminar si esa persona sigue al user
        UserFriend::where('follower_id', $blocked_id)
            ->where('followed_id', $blocker_id)
            ->delete();

        $this->updateFollowerCounts($blocker_id, $blocked_id);

        return response()->json(['message' => 'Has bloqueado a este usuario.']);
    }

    // DESBLOQUEAR a un usuario
    
    public function unblock($blocked_id)
    {
        $blocker_id = Auth::id();

        $relation = UserFriend::where('follower_id', $blocker_id)
            ->where('followed_id', $blocked_id)
            ->where('status', 'blocked')
            ->first();

        if (!$relation) { // Por si el usuario no estaba bloqueado pero el user le dio a bloquear
            return response()->json(['message' => 'Este usuario no estaba bloqueado.'], 404);
        }

        $relation->delete();
        $this->updateFollowerCounts($blocker_id, $blocked_id);

        return response()->json(['message' => 'Has desbloqueado a este usuario']);
    }

    // VER Lista de usuarios que user sigu-. FOLLOWERS
     
    public function followings($user_id = null)
    {
        $user_id = $user_id ?? Auth::id();

        $followings = UserFriend::where('follower_id', $user_id)
            ->where('status', 'accepted')
            ->with('followed:id,name,email')
            ->get()
            ->pluck('followed');

        return response()->json($followings);
    }

    // VER lista de FOLLOWINGS
    
    public function followers($user_id = null)
    {
        $user_id = $user_id ?? Auth::id();

        $followers = UserFriend::where('followed_id', $user_id)
            ->where('status', 'accepted')
            ->with('follower:id,name,email')
            ->get()
            ->pluck('follower');

        return response()->json($followers);
    }

    // VER Lista de usuarios bloqueados 
    
    public function blocked()
    {
        $user_id = Auth::id();

        $blocked = UserFriend::where('follower_id', $user_id)
            ->where('status', 'blocked')
            ->with('followed:id,name,email')
            ->get()
            ->pluck('followed');

        return response()->json($blocked);
    }

    // ACTUALIZAR los contadores de seguidores/seguido que se actualizará en UserProfile para fronted
     
    private function updateFollowerCounts($userA_id, $userB_id)
    {
        // Actualizamos los perfiles de ambos usuarios 
        $usersToUpdate = [$userA_id, $userB_id];

        foreach ($usersToUpdate as $userId) {
            //Buscamos el perfil
            $profile = UserProfile::where('user_id', $userId)->first();
            
            if ($profile) {
                // Contar cuántos siguen a este usuario sus followers
                $followers = UserFriend::where('followed_id', $userId)
                    ->where('status', 'accepted')
                    ->count();

                //Contar a cuántos sigue este usuario sus followings
                $followings = UserFriend::where('follower_id', $userId)
                    ->where('status', 'accepted')
                    ->count();

                //Asignar y Guardar
                $profile->followers_count = $followers;
                $profile->following_count = $followings; 
                
                $profile->save();
            }
        }
}

}