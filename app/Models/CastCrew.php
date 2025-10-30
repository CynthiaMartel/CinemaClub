<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastCrew extends Model
{
    protected $table = 'cast_crew';
    protected $primaryKey = 'idPerson';
    public $timestamps = true;

    protected $fillable = [
        'tmdb_id','name','bio','profile_path','birthday',
        'place_of_birth','photo'
    ];

    public function films()
    {
        return $this->belongsToMany(Film::class, 'film_cast_pivot', 'idPerson', 'idFilm')
                    ->withPivot('role', 'character_name', 'credit_order');
    }
}


