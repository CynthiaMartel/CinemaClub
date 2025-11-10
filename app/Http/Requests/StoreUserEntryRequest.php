<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Solo usuarios autenticados pueden crear entradas
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:user_list,user_debate,user_review',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'visibility' => 'in:public,friends,private',
            'allow_comments' => 'boolean',
            'cover_image' => 'nullable|string',
            'film_id' => 'nullable|integer|exists:films,idFilm', 
        ];
    }


    public function messages(): array
    {
        return [
            'type.required' => 'El tipo de entrada es obligatorio.',
            'title.required' => 'El título es obligatorio.',
            'title.max' => 'El título no puede superar los 255 caracteres.',
        ];
    }
}
