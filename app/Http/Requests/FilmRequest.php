<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilmRequest extends FormRequest
{
    public function authorize(): bool
    {
        // ** Más adelante para limitar a Admin/Editor:
        // return $this->user() && $this->user()->isAdminOrEditor();
        return true;
    }

    public function rules(): array
    {
        // Para poder ignorar la propia película en update (PUT/PATCH)
        $film   = $this->route('film'); // esto viene del route model binding de Laravel
        $filmId = $film?->idFilm ?? $film ?? null;

        $tmdbUniqueRule = Rule::unique('films', 'tmdb_id');
        $wikidataUniqueRule = Rule::unique('films', 'wikidata_id');

        if ($filmId) {
            // En update se ignora el registro actual
            $tmdbUniqueRule->ignore($filmId, 'idFilm');
            $wikidataUniqueRule->ignore($filmId, 'idFilm');
        }

        return [
            'tmdb_id' => [
                'nullable',
                'integer',
                $tmdbUniqueRule,
            ],
            'wikidata_id' => [
                'nullable',
                'integer',
                $wikidataUniqueRule,
            ],

            'title'           => ['required', 'string', 'max:255'],
            'original_title'  => ['required', 'string', 'max:255'],
            'genre'           => ['nullable', 'string', 'max:100'],
            'origin_country'  => ['nullable', 'string', 'max:100'],
            'original_language' => ['nullable', 'string', 'max:100'],
            'overview'        => ['nullable', 'string'],
            'duration'        => ['nullable', 'integer', 'min:1'],
            'release_date'    => ['nullable', 'date'],
            'frame'           => ['nullable', 'url'],

            'awards'      => ['nullable', 'array'],
            'nominations' => ['nullable', 'array'],
            'festivals'   => ['nullable', 'array'],

            'total_awards'      => ['nullable', 'integer', 'min:0'],
            'total_nominations' => ['nullable', 'integer', 'min:0'],
            'total_festivals'   => ['nullable', 'integer', 'min:0'],

            // Tomar los datos del cast de cast_crew
            'director_id' => ['required', 'exists:cast_crew,idPerson'],
            
            'cast'                     => ['nullable', 'array'], 
            'cast.*.idPerson'          => ['required', 'exists:cast_crew,idPerson'],
            'cast.*.role'              => ['required', 'string', 'max:100'],
            'cast.*.character_name'    => ['nullable', 'string', 'max:255'],
            'cast.*.credit_order'      => ['nullable', 'integer', 'min:0'],
            'cast.*.photo'             => ['nullable', 'url'],
            
            'vote_average'   => ['nullable', 'numeric', 'min:0', 'max:10'], // Nota media calculada a partir de los puntos dados por usuarios registrados
            'individualRate' => ['nullable', 'numeric', 'min:0', 'max:10'], // Nota del usuario logueado
            'globalRate'     => ['nullable', 'numeric', 'min:0', 'max:10'], // Nota global guardada en la API TMDB
        ];
    }

    public function messages(): array
    {
        return [
            'title.required'           => 'El título es obligatorio.',
            'title.max'                => 'El título no puede superar los 255 caracteres.',
            'original_title.required'  => 'El título original es obligatorio.',
            'original_title.max'       => 'El título original no puede superar los 255 caracteres.',

            'tmdb_id.integer'         => 'El TMDB ID debe ser un número entero.',
            'tmdb_id.unique'          => 'Ya existe una película con ese TMDB ID.',
            'wikidata_id.integer'     => 'El Wikidata ID debe ser un número entero.',
            'wikidata_id.unique'      => 'Ya existe una película con ese Wikidata ID.',

            'duration.integer'        => 'La duración debe ser un número entero.',
            'duration.min'            => 'La duración debe ser al menos 1 minuto.',

            'release_date.date'       => 'La fecha de estreno debe tener un formato de fecha válido.',

            'frame.url'               => 'La URL del fotograma/cartel no es válida.',

            'total_awards.integer'      => 'El total de premios debe ser un número entero.',
            'total_awards.min'          => 'El total de premios no puede ser negativo.',
            'total_nominations.integer' => 'El total de nominaciones debe ser un número entero.',
            'total_nominations.min'     => 'El total de nominaciones no puede ser negativo.',
            'total_festivals.integer'   => 'El total de festivales debe ser un número entero.',
            'total_festivals.min'       => 'El total de festivales no puede ser negativo.',

            'director_id.required'    => 'Debes indicar un director.',
            'director_id.exists'      => 'El director seleccionado no existe en la tabla cast_crew.',

            'cast.array'              => 'El reparto debe enviarse como un array.',
            'cast.*.idPerson.required'=> 'Cada miembro del reparto debe tener un idPerson.',
            'cast.*.idPerson.exists'  => 'Algún idPerson del reparto no existe en cast_crew.',
            'cast.*.role.required'    => 'Cada miembro del reparto debe tener un rol.',
            'cast.*.role.max'         => 'El rol no puede superar los 100 caracteres.',
            'cast.*.character_name.max'=> 'El nombre de personaje no puede superar los 255 caracteres.',
            'cast.*.credit_order.integer' => 'El orden de crédito debe ser un número entero.',
            'cast.*.credit_order.min'     => 'El orden de crédito no puede ser negativo.',
            'cast.*.photo.url'            => 'La URL de la foto del reparto no es válida.',

            'vote_average.numeric'    => 'La nota media debe ser numérica.',
            'vote_average.min'        => 'La nota media no puede ser menor que 0.',
            'vote_average.max'        => 'La nota media no puede ser mayor que 10.',

            'individualRate.numeric'  => 'La nota individual debe ser numérica.',
            'individualRate.min'      => 'La nota individual no puede ser menor que 0.',
            'individualRate.max'      => 'La nota individual no puede ser mayor que 10.',

            'globalRate.numeric'      => 'La nota global debe ser numérica.',
            'globalRate.min'          => 'La nota global no puede ser menor que 0.',
            'globalRate.max'          => 'La nota global no puede ser mayor que 10.',
        ];
    }
}
