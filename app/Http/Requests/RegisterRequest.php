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
            'name' => [
                'required',
                'string',
                'min:3',
                'max:30',
                // Reglas comunes en nombre de usuarios de registros en apps:
                // --Letras/números + separadores internos . _ -
                // -- No empieza ni termina con . _ -
                // -- No permite separadores repetidos (.. __ -- ._ etc)
                'regex:/^(?=.{3,30}$)[A-Za-z0-9]+(?:[._-][A-Za-z0-9]+)*$/',
                'unique:users,name',
            ],

            'email' => ['required', 'email', 'unique:users,email'],

            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&.]).+$/',
            ],

            'password_confirmation' => ['required'],
        ];
    }


    // Mensajes personalizados para mostrar los errores concretos de validación
    public function messages(): array
    {
        return [
            'name.required' => 'Debes rellenar tu nombre de usuario',
            'name.min' => 'El nombre de usuario debe tener al menos 3 caracteres',
            'name.max' => 'El nombre de usuario no puede superar 30 caracteres',
            'name.unique' => 'Ese nombre de usuario ya está en uso',
            'name.regex'  => 'Para nombre de usuario: Usa solo letras y números. Puedes incluir ".", "-" o "_" entre medias, pero no al principio o al final, y sin espacios',

            'email.required' => 'Debes rellenar tu email',
            'email.email' => 'El email no tiene un formato válido',
            'email.unique' => 'Ya existe una cuenta registrada con este email',

            'password.required' => 'Debes introducir una contraseña',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.regex' => 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial',
            'password.confirmed' => 'Las contraseñas no coinciden',

            'password_confirmation.required' => 'Debes confirmar la contraseña',
        ];
    }


}
