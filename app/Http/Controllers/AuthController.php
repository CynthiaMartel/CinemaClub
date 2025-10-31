<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use Carbon\Carbon;

class AuthController extends Controller
{
    // LOGIN: autentica al usuario y devuelve un token Sanctum

    public function login(Request $request)
    {
        // Validar campos para loguin con EMAIL y PASSWORD
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => [
                'required',
                'string',
                // Nota: Laravel permite poner condiciones en la validación:
                'min:8', // Mínimo 8 caracteres
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&]).+$/' // Al menos una mayúscula, una minúscula, un número y un símbolo especial
            ],
        ]);

        $email = $validated['email'];
        $password = $validated['password'];

        // LIMITADOR DE INTENTOS FALLIDOS: si usuario falla al loguearse más de 5 veces en 1 min., no se deja que el usuario vuelva a intentar el log durante unos segundos  
        // Nota: Sistema de protección temporal que no marca al usuario como bloqueado sino que evita ataque desde mismo IP o mismo mail un tiempo
        
        $throttleKey = 'login:'.strtolower($email).'|'.$request->ip(); // $throttleKey: Construcción de clave única para contar intentos de login (ej: login:cynthia@example.com|192.168.0.5)
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) { // Este if devuelve success 0 y un mensaje si se han superado los maxAttemps, 
            $seconds = RateLimiter::availableIn($throttleKey); // que en este caso son 5 (RateLimiter::tooManyAttempts($key, $maxAttempts))
            return response()->json([
                'success' => 0,
                'message' => "Demasiados intentos. Espera {$seconds} segundos."
            ], 429);
        }

        // Variable que busca usuario por email para manejarla en if de más adelante
        $user = User::where('email', $email)->first();

        // VALIDACIÓN DE USUARIO Y PASSWORD: Si usuario no existe o password incorrecta: aumenta contador de intentos fallidos
        // Nota: Bloquea cuenta si llega a más de 5 intentos y se penaliza IP temporalmente
        if (!$user || !Hash::check($password, $user->password)) { // Si password escrita no coincide con password hasheada guardada en la BD, entra en el if
                                                                //Nota: Hash::check() compara la contraseña escrita con el hash guardado en la BD, 
                                                                // ya que Laravel las guarda ya hasheadas. Devuelve true si hash guardado y password coinciden, false si no
                                                                                    
            RateLimiter::hit($throttleKey, 60); // Registramo en caché un intento fallido que durará 60 segundos (RateLimiter::hit($key, $decaySeconds))
            if ($user) { // Si usuario existe (email en BD), entramos en el if (pero su password escrita no coincidía con la hasheada en BD)
                $user->failedAttempts = $user->failedAttempts + 1; // Se incrementa el contador de fallos a 1
                if ($user->failedAttempts >= config('app.max_failed_attempts', 5)) { // Si el contador de fallos llega a 5, se marca el user como blocked
                    $user->blocked = true;
                }
                $user->save();
            }

            return response()->json([
                'success' => 0,
                'message' => 'Usuario o contraseña incorrectos'
            ], 401);
        }

        // Si el usuario está bloqueado success pasa a 0 y se lanza mensaje de bloqueado
        if ($user->blocked) {
            return response()->json([
                'success' => 0,
                'message' => 'Tu cuenta está bloqueada'
            ], 403);
        }

        // Si no entra en ningún if --> Restablecer intentos fallidos y actualizar última conexión
        $user->failedAttempts = 0;
        $user->dateHourLastAccess = Carbon::now();
        $user->ipLastAccess = $request->ip();
        $user->save();

        // Crear token para manejo en Sanctum: se guarda en la tabla personal_access_tokens, para próximas peticiones como votar por ej.
        $tokenName = 'login_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        // Respuesta json si no entra en ningún if:
        return response()->json([
            'success' => 1,
            'message' => 'Inicio de sesión exitoso. ¡Bienvenida!',
            'token'   => $token,
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'role'  => optional($user->role)->rolType,
            ],
        ], 200);
    }

    // LOGOUT: revoca el token actual // Nota: lleva mildware asegurando que el usuario haya iniciado sesión en rutas (auth:sanctum)
    
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => 1,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }

    // ME: para comprobar si el token Sanctum sigue siendo válido y obtener información del usuario logueado. //  Nota: lleva mildware asegurando que el usuario haya iniciado sesión en rutas (auth:sanctum)
    
    public function checkSession (Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => 1,
            'user' => [
                'id'            => $user->id,
                'name'          => $user->name,
                'email'         => $user->email,
                'role'          => optional($user->role)->rolType,
                'blocked'       => (bool) $user->blocked,
                'lastAccessAt'  => optional($user->dateHourLastAccess)->toDateTimeString(),
                'lastAccessIp'  => $user->ipLastAccess,
            ],
        ]);
    }
}
