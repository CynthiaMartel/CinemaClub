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
        $follower_id = Auth::id();

        if ($follower_id == $followed_id) {
            return response()->json(['error' => 'No puedes seguirte a ti misma.'], 400);
        }

        // Para ver si el seguido ha bloqueado
        $blocked = UserFriend::where('follower_id', $followed_id)
            ->where('followed_id', $follower_id)
            ->where('status', 'blocked')
            ->exists();

        if ($blocked) {
            return response()->json(['error' => 'No puedes seguir a este usuario.'], 403);
        }

        // Para evitar duplicados al seguir a un user
        $existing = UserFriend::where('follower_id', $follower_id)
            ->where('followed_id', $followed_id)
            ->first();

        if ($existing && $existing->status !== 'blocked') {
            return response()->json(['message' => 'Ya sigues a este usuario.'], 200);
        }

        // Para crear o reactivar la relación y que el status quede en 'accepted' (seguir a usuario)
        UserFriend::updateOrCreate(
            ['follower_id' => $follower_id, 'followed_id' => $followed_id],
            ['status' => 'accepted']
        );

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

    //BLOQUEAR a un usuario para evitar interacciones : si el user bloquea a otro, se marca el status como 'blocked' y se elimina de los seguidores del user
    
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

    // VER Lista de usuarios que user sigue. FOLLOWERS
     
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
     
    private function updateFollowerCounts($follower_id, $followed_id)
    {
        $followerCount = UserFriend::where('followed_id', $followed_id)
            ->where('status', 'accepted')
            ->count();

        $followingCount = UserFriend::where('follower_id', $follower_id)
            ->where('status', 'accepted')
            ->count();

        // Perfil FOLLOWING
        if ($followedProfile = UserProfile::where('user_id', $followed_id)->first()) {
            $followedProfile->followers_count = $followerCount;
            $followedProfile->save();
        }

        // Perfil FOLLOWER
        if ($followerProfile = UserProfile::where('user_id', $follower_id)->first()) {
            $followerProfile->followings_count = $followingCount;
            $followerProfile->save();
        }
    }
}

