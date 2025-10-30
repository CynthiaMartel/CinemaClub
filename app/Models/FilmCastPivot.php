<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilmCastPivot extends Model
{
    protected $table = 'film_cast_pivot';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'idFilm',
        'idPerson',
        'role',
        'character_name',
        'credit_order',
    ];
}


