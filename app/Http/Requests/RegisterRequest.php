<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Validaciones para usar en el registro de nuevos usuarios ResgisterController en Controllers/RegisterController.php
class RegisterRequest extends FormRequest
{
    // Autorizar el uso de este Request devolveindo true 
    public function authorize(): bool
    {
        return true;
    }

    // Reglas de validación de los campos en nombre, email y contraseña
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&.]).+$/'
,
            ],
            'repeatedPassword' => ['required', 'same:password'],
        ];
    }

    // Mensajes personalizados para mostrar los errores concretos de validación
    public function messages(): array
    {
        return [
            'name.required' => 'Debes rellenar tu nombre',
            'email.required' => 'Debes rellenar tu correo',
            'email.unique' => 'Ya existe una cuenta registrada con este correo',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial',
            'repeatedPassword.same' => 'Las contraseñas no coinciden',
        ];
    }
}
