<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserFilmActionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Todos los usuarios autenticados pueden usar este request.
        // Los permisos específicos se controlan en el controlador.
        return true;
    }

    public function rules(): array
    {
        return [
            'is_favorite'  => ['nullable', 'boolean'],
            'watch_later'  => ['nullable', 'boolean'],
            'watched'      => ['nullable', 'boolean'],
            'rating'       => ['nullable', 'integer', 'min:1', 'max:10'],
            'short_review' => ['nullable', 'string', 'max:500'],
            'visibility'   => ['nullable', 'in:public,friends,private'],
        ];
    }

    public function messages(): array
    {
        return [
            'is_favorite.boolean'  => 'El valor de favorito debe ser verdadero o falso.',
            'watch_later.boolean'  => 'El valor de "ver más tarde" debe ser verdadero o falso.',
            'watched.boolean'      => 'El valor de "vista" debe ser verdadero o falso.',
            'rating.integer'       => 'La puntuación debe ser un número entero.',
            'rating.min'           => 'La puntuación mínima es 1.',
            'rating.max'           => 'La puntuación máxima es 10.',
            'short_review.string'  => 'La reseña debe ser texto.',
            'short_review.max'     => 'La reseña no puede superar los 500 caracteres.',
            'visibility.in'        => 'La visibilidad debe ser pública, solo amigos o privada.',
        ];
    }
}
