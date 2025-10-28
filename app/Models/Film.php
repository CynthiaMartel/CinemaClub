<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Film extends Model
{
    protected $table = 'films';
    protected $primaryKey = 'idFilm';
    public $timestamps = true;
    protected $fillable = [
        'title','directedBy','genre','origin_country','original_language','overview',
        'duration','castCrew','release_date','frame','awards','nominations','festivals',
        'vote_average','individualRate','globalRate'
    ];

    public function individualRates()
    {
        return $this->hasMany(IndividualRate::class, 'idFilm', 'idFilm');
    }
}

