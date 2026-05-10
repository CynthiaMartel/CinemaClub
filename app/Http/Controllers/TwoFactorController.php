<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * GET /api/2fa/setup
     * Genera un secreto TOTP y devuelve el QR en SVG + el secreto en texto
     * para que el usuario lo introduzca manualmente si prefiere.
     * Solo accesible para roles 1 y 2 (admin y editor).
     */
    public function setup(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->idRol, [1, 2])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $secret = $this->google2fa->generateSecretKey();

        // Guardamos el secreto sin confirmar todavía (two_factor_confirmed_at = null)
        $user->two_factor_secret       = $secret;
        $user->two_factor_confirmed_at = null;
        $user->save();

        $otpUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrSvg  = $writer->writeString($otpUrl);

        return response()->json([
            'success' => true,
            'secret'  => $secret,
            'qr_svg'  => base64_encode($qrSvg),
        ]);
    }

    /**
     * POST /api/2fa/confirm
     * Verifica el primer código TOTP y activa el 2FA para la cuenta.
     */
    public function confirm(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->idRol, [1, 2])) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate(['code' => 'required|string|size:6']);

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Primero genera el código QR desde la configuración.'], 422);
        }

        $valid = $this->google2fa->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json(['message' => 'Código incorrecto. Comprueba que tu app de autenticación esté sincronizada.'], 422);
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json(['success' => true, 'message' => 'Autenticación en dos pasos activada correctamente.']);
    }

    /**
     * DELETE /api/2fa/disable
     * Desactiva el 2FA para la cuenta del usuario autenticado.
     */
    public function disable(Request $request)
    {
        $user = Auth::user();

        $user->two_factor_secret           = null;
        $user->two_factor_confirmed_at     = null;
        $user->two_factor_temp_token       = null;
        $user->two_factor_temp_token_expires_at = null;
        $user->save();

        return response()->json(['success' => true, 'message' => 'Autenticación en dos pasos desactivada.']);
    }

    /**
     * POST /api/2fa/verify
     * Segunda fase del login: recibe el temp_token + código TOTP y emite el token Sanctum real.
     * Ruta pública (no requiere auth:sanctum).
     */
    public function verify(Request $request)
    {
        $request->validate([
            'temp_token' => 'required|string',
            'code'       => 'required|string|size:6',
        ]);

        $user = \App\Models\User::where('two_factor_temp_token', $request->temp_token)
            ->where('two_factor_temp_token_expires_at', '>', now())
            ->first();

        if (!$user) {
            return response()->json(['success' => 0, 'message' => 'Token expirado o inválido. Inicia sesión de nuevo.'], 401);
        }

        $valid = (new Google2FA())->verifyKey($user->two_factor_secret, $request->code);

        if (!$valid) {
            return response()->json(['success' => 0, 'message' => 'Código incorrecto.'], 422);
        }

        // Limpiar el token temporal y emitir el token Sanctum real
        $user->two_factor_temp_token            = null;
        $user->two_factor_temp_token_expires_at = null;
        $user->save();

        $maxTokens = 5;
        $tokens = $user->tokens()->orderBy('created_at', 'asc')->get();
        if ($tokens->count() >= $maxTokens) {
            $tokens->take($tokens->count() - $maxTokens + 1)->each->delete();
        }

        $token = $user->createToken('login_token')->plainTextToken;

        $cookieMinutes = 60 * 24 * 7;
        $secure        = app()->environment('production');

        $user->load('profile:user_id,avatar');

        return response()->json([
            'success' => 1,
            'message' => 'Verificación completada. ¡Bienvenida!',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
                'idRol' => $user->idRol,
                'role'  => optional($user->role)->rolType,
                'avatar' => $user->profile?->avatar,
            ],
        ])->withCookie(
            cookie('auth_token', $token, $cookieMinutes, '/', null, $secure, true, false, 'lax')
        );
    }
}
