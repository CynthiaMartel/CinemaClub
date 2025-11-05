<?php

namespace App\Http\Controllers;


use App\Http\Requests\RegisterRequest;  
use App\Models\User;
use App\Http\Requests\ChangePasswordRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;

class ChangePasswordController extends Controller

{
    public function update(ChangePasswordRequest $request)
    {
        $user = $request->user();

        // Para verificar que la contraseña actual sea correcta: si no fuese correcta, se devuelve un error 401 y no se continua
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => 0,
                'message' => 'La contraseña actual es incorrecta.'
            ], 401);
        }

        // Para actualizar con nueva contraseña hasheada 
        $user->password = Hash::make($request->new_password);
        $user->password_changed_at = now();     
        $user->save();

        // Para mayor seguridad, invalidamos tokens antiguos (por si hay alguna ssesión abierta en algún otro dispositivo con la contraseña antigua)
        //$user->tokens()->delete();

        // Crear nuevo token para mantener sesión activa y el usuario no pierda la sesión ACTUAL
        //$newToken = $user->createToken('password_changed_token')->plainTextToken;

        return response()->json([
            'success' => 1,
            'message' => 'Contraseña actualizada correctamente.',
            //'token' => $newToken
        ], 200);
    }
}
