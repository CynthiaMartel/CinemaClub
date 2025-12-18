<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validaciones para usar en el LOGIN en Controllers/AuthController.php

class AuthRequest extends FormRequest
{
    // Autorizar el uso de este Request devolveindo true 
    public function authorize(): bool
    {
        return true;
    }

    // Reglas de validación para que el usuario haga login
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'exists:users,email'],
            'password' => ['required', 'string'],
        ];
    }


     // Mensajes personalizados para mostrar los errores concretos de validación
    public function messages(): array
    {
        return [
            'email.required' => 'Debes escribir tu correo electrónico.',
            'email.email' => 'El formato del correo no es válido. Revísalo.',
            'email.exists' => 'No existe ninguna cuenta con este correo.',
            'password.required' => 'Debes escribir tu contraseña.',
        ];
    }

}

