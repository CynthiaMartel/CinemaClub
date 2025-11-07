<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UserProfileRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta petición.
     */
    public function authorize(): bool
    {
        // Solo usuarios autenticados pueden hacer estas acciones
        return Auth::check();
    }

    /**
     * Reglas de validación para creación/actualización de perfil.
     */
    public function rules(): array
    {
        $rules = [
            'avatar' => 'nullable|image|max:2048',
            'bio' => 'nullable|string|max:1000',
            'location' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'top_5_films' => 'nullable|array|max:5',
        ];

        // En creación (store) se valida también el user_id
        if ($this->isMethod('post')) {
            $rules['user_id'] = 'required|exists:users,id|unique:user_profiles,user_id';
        }

        return $rules;
    }

    /**
     * Mensajes personalizados (opcional)
     */
    public function messages(): array
    {
        return [
            'avatar.image' => 'El archivo debe ser una imagen válida.',
            'avatar.max' => 'La imagen no debe superar los 2 MB.',
            'bio.max' => 'La biografía no puede tener más de 1000 caracteres.',
            'website.url' => 'El campo sitio web debe ser una URL válida.',
            'top_5_films.array' => 'El top 5 debe ser una lista válida de películas.',
        ];
    }
}

