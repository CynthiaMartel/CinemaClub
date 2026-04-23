<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FilmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->isAdminOrEditor();
    }

    /**
     * Normaliza los campos numéricos con DEFAULT en BD (NOT NULL) antes de
     * que lleguen a las reglas de validación. Así nunca se inserta NULL en
     * una columna que no lo admite, evitando errores de integridad con MySQL
     * en modo strict.
     */
    protected function prepareForValidation(): void
    {
        $intDefaults = [
            'total_awards'      => 0,
            'total_nominations' => 0,
            'total_festivals'   => 0,
        ];
        $floatDefaults = [
            'vote_average' => 0,
            'globalRate'   => 0,
        ];

        $merge = [];
        foreach ($intDefaults as $field => $default) {
            if ($this->input($field) === null || $this->input($field) === '') {
                $merge[$field] = $default;
            }
        }
        foreach ($floatDefaults as $field => $default) {
            if ($this->input($field) === null || $this->input($field) === '') {
                $merge[$field] = $default;
            }
        }
        if ($merge) {
            $this->merge($merge);
        }
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
            // smallInteger unsigned → max 65 535; min:1 porque 0 no tiene sentido
            'duration'        => ['nullable', 'integer', 'min:1', 'max:65535'],
            'release_date'    => ['nullable', 'date'],
            // frame: varchar(225) en BD → máximo 225 chars; url para garantizar formato
            'frame'           => ['nullable', 'url', 'max:225'],
            // backdrop: varchar(255) en BD
            'backdrop'        => ['nullable', 'url', 'max:255'],

            'awards'      => ['nullable', 'array'],
            'nominations' => ['nullable', 'array'],
            'festivals'   => ['nullable', 'array'],

            // NOT NULL DEFAULT 0 en BD → prepareForValidation() garantiza que nunca lleguen como null
            'total_awards'      => ['required', 'integer', 'min:0', 'max:65535'],
            'total_nominations' => ['required', 'integer', 'min:0', 'max:65535'],
            'total_festivals'   => ['required', 'integer', 'min:0', 'max:65535'],

            // Tomar los datos del cast de cast_crew
            'director_id' => ['nullable', 'integer', 'exists:cast_crew,idPerson'],

            'cast'                     => ['nullable', 'array'],
            'cast.*.idPerson'          => ['required', 'exists:cast_crew,idPerson'],
            'cast.*.role'              => ['required', 'string', 'max:100'],
            'cast.*.character_name'    => ['nullable', 'string', 'max:255'],
            'cast.*.credit_order'      => ['nullable', 'integer', 'min:0'],
            'cast.*.photo'             => ['nullable', 'url'],

            // NOT NULL DEFAULT 0 en BD → prepareForValidation() garantiza que nunca lleguen como null
            'vote_average'   => ['required', 'numeric', 'min:0', 'max:10'],
            'globalRate'     => ['required', 'numeric', 'min:0', 'max:10'],
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
            'duration.max'            => 'La duración no puede superar 65 535 minutos.',

            'release_date.date'       => 'La fecha de estreno debe tener un formato de fecha válido.',

            'frame.url'               => 'La URL del póster no es válida (debe empezar por https://).',
            'frame.max'               => 'La URL del póster no puede superar los 225 caracteres.',
            'backdrop.url'            => 'La URL del backdrop no es válida (debe empezar por https://).',
            'backdrop.max'            => 'La URL del backdrop no puede superar los 255 caracteres.',

            'total_awards.integer'      => 'El total de premios debe ser un número entero.',
            'total_awards.min'          => 'El total de premios no puede ser negativo.',
            'total_awards.max'          => 'El total de premios no puede superar 65 535.',
            'total_nominations.integer' => 'El total de nominaciones debe ser un número entero.',
            'total_nominations.min'     => 'El total de nominaciones no puede ser negativo.',
            'total_nominations.max'     => 'El total de nominaciones no puede superar 65 535.',
            'total_festivals.integer'   => 'El total de festivales debe ser un número entero.',
            'total_festivals.min'       => 'El total de festivales no puede ser negativo.',
            'total_festivals.max'       => 'El total de festivales no puede superar 65 535.',

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

            'globalRate.numeric'      => 'La nota global debe ser numérica.',
            'globalRate.min'          => 'La nota global no puede ser menor que 0.',
            'globalRate.max'          => 'La nota global no puede ser mayor que 10.',
        ];
    }
}
