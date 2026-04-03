<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;  
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

use Carbon\Carbon;

use App\Models\UserProfile;
use App\Mail\WelcomeUserMail;


class RegisterController extends Controller
{
    // Método para crear una nueva cuenta de usuario
    public function register(RegisterRequest $request)
    {
        // Los datos ya vienen validados por RegisterRequest (Request/RegisterRquest.php)
        // Si hay errores, Laravel devolverá JSON 422 automáticamente

        // Creación del nuevo usuario
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = Hash::make($request->password);
        $user->idRol = 3; // Rol por defecto "User"
        $user->ipLastAccess = $request->ip();
        $user->dateHourLastAccess = Carbon::now();
        $user->save();

        // Creación automática de perfil de usuario pero VACÍA
        UserProfile::create([
        'user_id'     => $user->id,
        'bio'         => null,
        'location'    => null,
        'website'     => null,
        'top_films' => [], 
    ]);

        // Para enviar correo de bienvenida 
        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user));
        } catch (\Exception $e) {
            \Log::warning("Error enviando correo de bienvenida a {$user->email}: ".$e->getMessage());
        }

        // Crear token Sanctum y enviarlo como cookie HttpOnly
        $token = $user->createToken('register_token')->plainTextToken;

        $cookieMinutes = 60 * 24 * 7;
        $secure        = app()->environment('production');

        return response()->json([
            'success' => 1,
            'message' => '¡Cuenta creada con éxito! Te damos la bienvenida.',
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'idRol' => $user->idRol,
                'role'  => optional($user->role)->rolType,
            ]
        ], 201)->withCookie(
            cookie('auth_token', $token, $cookieMinutes, '/', null, $secure, true, false, 'lax')
        );
    }
}

