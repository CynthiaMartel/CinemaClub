<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validaciones para usar en CAMBIO DE CONTRASEÑA (CHANGE PASSWORD) en Controllers/ChangePasswordController.php
class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        // // Autorizar el uso de este Request devolveindo true para que usuario autenticado pueda cambiar contraseña
        return true;
    }

    // Reglas de validación de los campos en passqord actual, contraseña nueva
    public function rules(): array
    {
        return [
            'current_password' => ['required'],
            'new_password' => [
                'required',
                'string',
                'min:8',
                'different:current_password',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&.]).+$/'
            ],
            'confirm_password' => ['required', 'same:new_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Debes ingresar tu contraseña actual.',
            'new_password.required' => 'Debes ingresar la nueva contraseña.',
            'new_password.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
            'new_password.different' => 'La nueva contraseña no puede ser igual a la actual.',
            'new_password.regex' => 'Debe contener al menos una mayúscula, una minúscula, un número y un carácter especial.',
            'confirm_password.required' => 'Debes confirmar la nueva contraseña.',
            'confirm_password.same' => 'El campo de nueva contraseña debe coincidir con el campo de confirmación de nueva contraseña.',
        ];
    }
}

