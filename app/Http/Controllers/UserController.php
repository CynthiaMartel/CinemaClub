<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserProfile;
use App\Http\Requests\UserAdminRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    //Listar usuarios con filtros básicos
    // Solo para Admin
    // Filtros:
     // q: nombre y/o email
     // role: idRol
     // blocked: 0/1
    
    public function index(Request $request)
    {
        $currentUser = $request->user();

        // Sólo puede entrar si es ADMIN
        if (!$currentUser || !$currentUser->isAdmin()) {
            return response()->json([
                'success' => 0,
                'message' => 'No tienes permiso para ver el listado de usuarios.',
            ], 403);
        }

        $query = User::with('role', 'profile');

        if ($request->filled('q')) {
            $q = trim($request->get('q'));

            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('idRol', $request->get('role'));
        }

        if ($request->filled('blocked')) {
            $blocked = (bool) $request->get('blocked');
            $query->where('blocked', $blocked);
        }

        $users = $query->orderBy('name')->paginate(20);

        return response()->json([
            'success' => 1,
            'data'    => $users,
        ]);
    }

    /**
     * Búsqueda para barra de búsqueda.
     * -- ADMIN: puede ver todos los roles y bloqueados
     * - USER normal (es decir, role "User"): sólo ve usuarios con rol "User" y no bloqueados
     */
    public function search(Request $request)
    {
        $currentUser = $request->user();
        $viewerIsAdmin = $currentUser && $currentUser->isAdmin();

        $q = trim($request->get('q', ''));

        $query = User::with('profile', 'role');

        if ($q !== '') {
            $query->where(function ($sub) use ($q, $currentUser, $viewerIsAdmin) {
                $sub->where('name', 'like', "%{$q}%");

                // Si quien busca es ADMIN, puede buscar también por email
                if ($viewerIsAdmin) {
                    $sub->orWhere('email', 'like', "%{$q}%");
                }
            });
        }

        // Si NO es admin, sólo usuarios regulares (rol "User") no bloqueados
        if (!$viewerIsAdmin) {
            $query->where('blocked', false)
                  ->whereHas('role', function ($r) {
                      $r->where('rolType', 'User');
                  });
        }

        $users = $query->orderBy('name')->limit(20)->get();

        $formatted = $users->map(function (User $user) use ($viewerIsAdmin) {
            return [
                'id'      => $user->id,
                'name'    => $user->name,
                'avatar'  => optional($user->profile)->avatar ?? null,
                'bio'     => optional($user->profile)->bio,
                'email'   => $viewerIsAdmin ? $user->email : null,
                'blocked' => $viewerIsAdmin ? (bool) $user->blocked : null,
                'role'    => optional($user->role)->rolType,
            ];
        });

        return response()->json([
            'success' => 1,
            'data'    => $formatted,
        ]);
    }

    /**
     * Mostrar info detallada de un usuario.
     * --ADMIN: puede ver todos (incluidos admins, editors, bloqueados
     * -- USER normal: NO puede ver ADMINS ni EDITORS ni usuarios blocked
     */
    public function show(Request $request, User $user)
    {
        $currentUser = $request->user();
        $viewerIsAdmin = $currentUser && $currentUser->isAdmin();

        $user->load('role', 'profile');

        if (!$viewerIsAdmin) {
            // No puede ver perfiles bloqueados
            if ($user->blocked) {
                return response()->json([
                    'success' => 0,
                    'message' => 'No tienes permiso para ver este perfil.',
                ], 403);
            }

            // No puede ver perfiles de Admin ni Editor
            if ($user->isAdmin() || $user->isEditor()) {
                return response()->json([
                    'success' => 0,
                    'message' => 'No tienes permiso para ver este perfil.',
                ], 403);
            }
        }

        return response()->json([
            'success' => 1,
            'data'    => $user,
        ]);
    }

    // Crear usuario desde panel (solo ADMIN).
     
    public function store(UserAdminRequest $request)
    {
        $currentUser = $request->user();

        if (!($currentUser && $currentUser->isAdmin())) {
            return response()->json([
                'success' => 0,
                'message' => 'No tienes permiso para crear usuarios.',
            ], 403);
        }

        $data = $request->validated();

        $data['password']       = Hash::make($data['password']);
        $data['failedAttempts'] = $data['failedAttempts'] ?? 0;
        $data['blocked']        = $data['blocked'] ?? false;

        unset($data['repeatedPassword']);

        $user = User::create($data);

        UserProfile::create([
            'user_id'              => $user->id,
            'bio'                  => null,
            'location'             => null,
            'website'              => null,
            'top_5_films'          => json_encode([]),
            'films_seen'           => 0,
            'films_rated'          => 0,
            'films_seen_this_year' => 0,
            'lists_created'        => 0,
            'lists_saved'          => 0,
            'followers_count'      => 0,
            'followings_count'     => 0,
        ]);

        return response()->json([
            'success' => 1,
            'message' => 'Usuario creado correctamente.',
            'data'    => $user->load('role', 'profile'),
        ], 201);
    }

    /**
     * Actualizar datos de un usuario (SOLO ADMIN)
     * La contraseña "normal" se cambia con ChangePasswordController, pero
     * aquí el admin puede resetearla. ********************************************* PENDIENTE: ENVIAR UN CORREO AL USER CUANDO EL ADMIN CAMBIE LA PASSWORD
     */
    public function update(UserAdminRequest $request, User $user)
    {
        $currentUser = $request->user();

        if (!($currentUser && $currentUser->isAdmin())) {
            return response()->json([
                'success' => 0,
                'message' => 'No tienes permiso para actualizar usuarios.',
            ], 403);
        }

        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password']            = Hash::make($data['password']);
            $data['password_changed_at'] = now();
        } else {
            unset($data['password']);
        }

        unset($data['repeatedPassword']);

        $user->update($data);

        return response()->json([
            'success' => 1,
            'message' => 'Usuario actualizado correctamente.',
            'data'    => $user->fresh('role', 'profile'),
        ]);
    }

    // Bloqueo manual por admin (en función futura, tal vez como moderador)
    public function block(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!($currentUser && $currentUser->isAdmin())) {
            return response()->json([
                'success' => 0,
                'message' => 'No tienes permiso para bloquear usuarios.',
            ], 403);
        }

        $user->blocked = true;
        $user->save();

        return response()->json([
            'success' => 1,
            'message' => 'Usuario bloqueado correctamente.',
            'data'    => $user,
        ]);
    }

    // Desbloqueo manual por ADMIN
    
    public function unblock(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!($currentUser && $currentUser->isAdmin())) {
            return response()->json([
                'success' => 0,
                'message' => 'No tienes permiso para desbloquear usuarios.',
            ], 403);
        }

        $user->blocked        = false;
        $user->failedAttempts = 0;
        $user->save();

        return response()->json([
            'success' => 1,
            'message' => 'Usuario desbloqueado correctamente.',
            'data'    => $user,
        ]);
    }

    // Eliminar cuenta SOLO ADMIN
     
    public function destroy(Request $request, User $user)
    {
        $currentUser = $request->user();

        if (!($currentUser && $currentUser->isAdmin())) {
            return response()->json([
                'success' => 0,
                'message' => 'No tienes permiso para eliminar cuentas.',
            ], 403);
        }

        $user->profile()->delete();
        $user->delete();

        return response()->json([
            'success' => 1,
            'message' => 'Cuenta eliminada correctamente.',
        ]);
    }
}



