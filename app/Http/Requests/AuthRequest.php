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
            'identifier' => ['required', 'string'],
            'password'   => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'identifier.required' => 'Debes escribir tu email o nombre de usuario.',
            'password.required'   => 'Debes escribir tu contraseña.',
        ];
    }

}

