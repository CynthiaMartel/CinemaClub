<?php

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\User;
use Carbon\Carbon;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Mail\AccountBlockedMail;

class AuthController extends Controller
{
    const MAX_ACTIVE_TOKENS = 5;

    private function verifyTurnstile(Request $request): bool
    {
        if (app()->environment('local', 'testing')) {
            return true;
        }

        $token = $request->input('cf_turnstile_response');
        if (empty($token)) {
            return false;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret'   => config('services.turnstile.secret'),
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        return $response->json('success', false) === true;
    }

    public function login(AuthRequest $request)
    {
        $identifier = $request->identifier;
        $password   = $request->password;

        if (!$this->verifyTurnstile($request)) {
            return response()->json([
                'success' => 0,
                'message' => 'Verificación de seguridad fallida. Por favor, inténtalo de nuevo.',
            ], 422);
        }

        $throttleKey = 'login:' . strtolower($identifier) . '|' . $request->ip();
        $maxAttempts = config('app.max_failed_attempts', 5);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'success' => 0,
                'message' => "Demasiados intentos. Espera {$seconds} segundos.",
            ], 429);
        }

        // Buscar por email o por nombre de usuario
        $user = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $identifier)->first()
            : User::where('name', $identifier)->first();

        // Mensaje genérico para no revelar si el email existe (evita enumeración)
        $invalidMsg = 'Usuario o contraseña incorrectos';

        if (!$user || !Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            $justBlockedNow = false;

            if ($user) {
                $user->failedAttempts = $user->failedAttempts + 1;

                if ($user->failedAttempts >= $maxAttempts) {
                    if (!$user->blocked) {
                        $justBlockedNow = true;
                    }
                    $user->blocked = true;
                }

                $user->save();

                if ($justBlockedNow) {
                    try {
                        Mail::to($user->email)->send(new AccountBlockedMail($user));
                    } catch (\Exception $e) {
                        \Log::warning("Error enviando correo de bloqueo a {$user->email}: " . $e->getMessage());
                    }
                }
            }

            return response()->json(['success' => 0, 'message' => $invalidMsg], 401);
        }

        if ($user->blocked) {
            return response()->json([
                'success' => 0,
                'message' => 'Tu cuenta está bloqueada. Contacta con el soporte.',
            ], 403);
        }

        // Bloquear login si el email no ha sido verificado
        if (is_null($user->email_verified_at)) {
            return response()->json([
                'success' => 0,
                'message' => 'Debes verificar tu email antes de iniciar sesión. Revisa tu bandeja de entrada.',
            ], 403);
        }

        $user->failedAttempts     = 0;
        $user->dateHourLastAccess = Carbon::now();
        $user->ipLastAccess       = $request->ip();
        $user->save();

        // Si el usuario tiene 2FA activo, emitir token temporal en lugar del Sanctum real
        if ($user->hasTwoFactorEnabled()) {
            $tempToken = Str::random(64);
            $user->two_factor_temp_token            = $tempToken;
            $user->two_factor_temp_token_expires_at = Carbon::now()->addMinutes(5);
            $user->save();

            return response()->json([
                'success'    => 2,
                'requires_2fa' => true,
                'temp_token' => $tempToken,
            ], 200);
        }

        // Revocar tokens más antiguos si se supera el límite de sesiones concurrentes
        $tokens = $user->tokens()->orderBy('created_at', 'asc')->get();
        if ($tokens->count() >= self::MAX_ACTIVE_TOKENS) {
            $tokens->take($tokens->count() - self::MAX_ACTIVE_TOKENS + 1)->each->delete();
        }

        $token = $user->createToken('login_token')->plainTextToken;

        $cookieMinutes = 60 * 24 * 7;
        $secure        = app()->environment('production');

        $user->load('profile:user_id,avatar');

        return response()->json([
            'success' => 1,
            'message' => 'Inicio de sesión exitoso. ¡Bienvenida!',
            'user'    => [
                'id'               => $user->id,
                'name'             => $user->name,
                'email'            => $user->email,
                'idRol'            => $user->idRol,
                'role'             => optional($user->role)->rolType,
                'avatar'           => $user->profile?->avatar,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],
        ], 200)->withCookie(
            cookie('auth_token', $token, $cookieMinutes, '/', null, $secure, true, false, 'lax')
        );
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => 1,
            'message' => 'Sesión cerrada correctamente',
        ])->withCookie(cookie()->forget('auth_token'));
    }

    public function checkSession(Request $request)
    {
        $user = $request->user();
        $user->load('profile:user_id,avatar');

        return response()->json([
            'success' => 1,
            'user' => [
                'id'      => $user->id,
                'name'    => $user->name,
                'email'   => $user->email,
                'idRol'   => $user->idRol,
                'role'               => optional($user->role)->rolType,
                'blocked'            => (bool) $user->blocked,
                'avatar'             => $user->profile?->avatar,
                'two_factor_enabled' => $user->hasTwoFactorEnabled(),
            ],
        ]);
    }
}
