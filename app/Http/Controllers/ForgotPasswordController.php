<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Mail\PasswordResetMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ForgotPasswordController extends Controller
{
    // POST /api/forgot-password
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $request->email)->first();

        // Respuesta genérica siempre para no revelar si el email existe
        $genericResponse = response()->json([
            'success' => 1,
            'message' => 'Si existe una cuenta con ese email, recibirás un enlace para restablecer tu contraseña.',
        ]);

        if (!$user) {
            return $genericResponse;
        }

        // Throttle: solo 1 solicitud cada 5 minutos por email
        $recent = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->where('created_at', '>', Carbon::now()->subMinutes(5))
            ->exists();

        if ($recent) {
            return $genericResponse;
        }

        $token = Str::random(64);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => $token, 'created_at' => Carbon::now()]
        );

        $resetUrl = rtrim(config('app.frontend_url'), '/') . '/reset-password?token=' . $token . '&email=' . urlencode($user->email);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user->name, $resetUrl));
        } catch (\Exception $e) {
            \Log::warning("Error enviando reset de contraseña a {$user->email}: " . $e->getMessage());
        }

        return $genericResponse;
    }

    // POST /api/reset-password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => ['required', 'string'],
            'email'                 => ['required', 'email'],
            'password'              => [
                'required', 'string', 'min:8', 'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&.]).+$/',
            ],
            'password_confirmation' => ['required'],
        ], [
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial.',
        ]);

        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('token', $request->token)
            ->first();

        if (!$record) {
            return response()->json([
                'success' => 0,
                'message' => 'El enlace no es válido o ya fue utilizado.',
            ], 422);
        }

        // Token expira en 1 hora
        if (Carbon::parse($record->created_at)->addHour()->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json([
                'success' => 0,
                'message' => 'El enlace ha caducado. Solicita uno nuevo.',
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json(['success' => 0, 'message' => 'Usuario no encontrado.'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Revocar todos los tokens activos para forzar re-login
        $user->tokens()->delete();

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => 1,
            'message' => 'Contraseña restablecida con éxito. Ya puedes iniciar sesión.',
        ]);
    }
}
