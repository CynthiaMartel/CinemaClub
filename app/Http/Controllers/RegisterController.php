<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Models\UserProfile;
use App\Mail\VerifyEmailMail;
use Illuminate\Support\Facades\Http;

class RegisterController extends Controller
{
    private function verifyTurnstile(RegisterRequest $request): bool
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

    public function register(RegisterRequest $request)
    {
        if (!$this->verifyTurnstile($request)) {
            return response()->json([
                'success' => 0,
                'message' => 'Verificación de seguridad fallida. Por favor, inténtalo de nuevo.',
            ], 422);
        }

        $token = Str::random(64);

        $user = new User();
        $user->name               = $request->name;
        $user->email              = $request->email;
        $user->password           = Hash::make($request->password);
        $user->idRol              = 3;
        $user->ipLastAccess       = $request->ip();
        $user->dateHourLastAccess = Carbon::now();
        $user->verification_token = $token;
        $user->email_verified_at  = null;
        $user->save();

        UserProfile::create([
            'user_id'   => $user->id,
            'bio'       => null,
            'location'  => null,
            'website'   => null,
            'top_films' => [],
        ]);

        // El enlace va a una ruta del SPA que luego llama a la API
        $verificationUrl = rtrim(config('app.frontend_url'), '/') . '/verify-email/' . $token;

        try {
            Mail::to($user->email)->send(new VerifyEmailMail($user, $verificationUrl));
        } catch (\Exception $e) {
            \Log::warning("Error enviando correo de verificación a {$user->email}: " . $e->getMessage());
        }

        return response()->json([
            'success' => 1,
            'message' => 'Cuenta creada. Revisa tu email y haz clic en el enlace de verificación para activarla.',
        ], 201);
    }
}
