<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;  
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;

use Carbon\Carbon;


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

        // Para enviar correo de bienvenida ***** POR HACER
        try {
            // **** POR HACER
        } catch (\Exception $e) {
            \Log::warning("Error enviando correo de bienvenida a {$user->email}: ".$e->getMessage());
        }

        // Crear token Sanctum para autenticar automáticamente tras registro de nuevo usuario
        $token = $user->createToken('register_token')->plainTextToken;

        // Devolver respuesta JSON para creación de nuevo usuario
        return response()->json([
            'success' => 1,
            'message' => '¡Cuenta creada con éxito! Te damos la bienvenida.',
            'token' => $token,
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => optional($user->role)->rolType,
            ]
        ], 201); // Error 201 si success no es 1
    }
}

