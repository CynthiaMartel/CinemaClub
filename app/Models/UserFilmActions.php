<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class UserFilmActions extends Model
{
    use HasFactory;
   
    protected $table = 'user_film_actions';


    protected $fillable = [
        'user_id',
        'film_id',
        'is_favorite',
        'watch_later',
        'watched',
        'rating',
        'short_review',
        'visibility',
    ];

   
    protected $casts = [
        'is_favorite' => 'boolean',
        'watch_later' => 'boolean',
        'watched'     => 'boolean',
        'rating'      => 'integer',
    ];

  
    public function user()
    {
        return $this->belongsTo(User::class); // **Si ponemos la convección de laravel user_id o film_id, no hace falta que pongamos User:class, user_id porque lo busca automáticamente
    }

    public function film()
    {
        return $this->belongsTo(Film::class, 'film_id', 'idFilm');
    }

   
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    
    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeWatchLater($query)
    {
        return $query->where('watch_later', true);
    }

    
    public function scopeWatched($query)
    {
        return $query->where('watched', true);
    }

    public function scopeRated($query)
    {
        return $query->whereNotNull('rating');
    }
}


