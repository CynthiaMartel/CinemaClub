<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        // // ** Más adelante para limitar a Admin:
        // return $this->user() && $this->user()->isAdmin();
        return true;
    }

    public function rules(): array
    {
        //Para poder ignorar el email del propio usuario en update:
        $user   = $this->route('user'); // puede ser modelo o id
        $userId = $user?->id ?? $user ?? null;

        // Password: obligatoria sólo al crear (POST), opcional al actualizar (PUT/PATCH)
        $passwordRules = $this->isMethod('post')
            ? [
                'required',
                'string',
                'min:8',
                // Mismo patrón de seguridad que en RegisterRequest y ChangePasswordRequest
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&.]).+$/',
            ]
            : [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*#?&.]).+$/',
            ];

        $repeatPasswordRules = $this->isMethod('post')
            ? ['required', 'same:password']
            : ['nullable', 'same:password'];

        return [
            'name' => ['required', 'string', 'max:150'],

            'email' => [
                'required',
                'email',
                'max:50',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            'password'         => $passwordRules,
            'repeatedPassword' => $repeatPasswordRules,

            'idRol' => [
                'required',
                'integer',
                'exists:rol,id',
            ],

            // Estos campos son más administrativos, opcionales **
            'blocked'        => ['sometimes', 'boolean'],
            'failedAttempts' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Debes rellenar el nombre.',
            'name.max'      => 'El nombre no puede superar los 150 caracteres.',

            'email.required' => 'Debes rellenar el correo.',
            'email.email'    => 'El formato del correo no es válido.',
            'email.max'      => 'El correo no puede superar los 50 caracteres.',
            'email.unique'   => 'Ya existe una cuenta registrada con este correo.',

            'password.required' => 'Debes ingresar una contraseña.',
            'password.min'      => 'La contraseña debe tener al menos 8 caracteres.',
            'password.regex'    => 'La contraseña debe contener al menos una mayúscula, una minúscula, un número y un carácter especial.',

            'repeatedPassword.required' => 'Debes repetir la contraseña.',
            'repeatedPassword.same'     => 'Las contraseñas no coinciden.',

            'idRol.required' => 'El rol es obligatorio.',
            'idRol.exists'   => 'El rol seleccionado no existe.',

            'blocked.boolean'        => 'El campo "blocked" debe ser verdadero o falso.',
            'failedAttempts.integer' => 'Los intentos fallidos deben ser un número entero.',
            'failedAttempts.min'     => 'Los intentos fallidos no pueden ser negativos.',
        ];
    }
}

