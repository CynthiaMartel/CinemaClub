<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Film extends Model
{
    protected $table = 'films';
    protected $primaryKey = 'idFilm';
    public $timestamps = true;

    protected $fillable = [
        'tmdb_id','wikidata_id', 'title','original_title', 'genre','origin_country','original_language','overview',
        'duration','release_date','frame','awards','nominations','festivals',
        'total_awards','total_nominations','total_festivals',
        'vote_average','individualRate','globalRate','director_id'
    ];

    public function individualRates()
    {
        return $this->hasMany(IndividualRate::class, 'idFilm', 'idFilm');
    }

    public function cast()
    {
        return $this->belongsToMany(CastCrew::class, 'film_cast_pivot', 'idFilm', 'idPerson')
                    ->withPivot('role', 'character_name', 'credit_order')
                    ->orderBy('film_cast_pivot.credit_order');
    }

    public function directors()
    {
        return $this->cast()->wherePivot('role', 'Director');
    }


    public function comments()
    {
        return $this->morphMany(UserComment::class, 'commentable');
    }

}


