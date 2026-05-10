<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\WelcomeUserMail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class VerifyEmailController extends Controller
{
    public function verify(string $token)
    {
        $user = User::where('verification_token', $token)->first();

        if (!$user) {
            return response()->json([
                'success' => 0,
                'message' => 'El enlace de verificación no es válido.',
            ], 422);
        }

        if (Carbon::parse($user->dateHourLastAccess)->diffInHours(Carbon::now()) > 24) {
            return response()->json([
                'success' => 0,
                'message' => 'El enlace ha caducado. Regístrate de nuevo.',
            ], 422);
        }

        $user->email_verified_at  = Carbon::now();
        $user->verification_token = null;
        $user->save();

        try {
            Mail::to($user->email)->send(new WelcomeUserMail($user));
        } catch (\Exception $e) {
            \Log::warning("Error enviando correo de bienvenida a {$user->email}: " . $e->getMessage());
        }

        return response()->json([
            'success' => 1,
            'message' => '¡Email verificado! Ya puedes iniciar sesión.',
        ]);
    }
}
