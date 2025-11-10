<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEntryFilm extends Model
{
    use HasFactory;

    protected $table = 'user_entry_films';

    protected $fillable = [
        'user_entry_id',
        'film_id',
        'order',
    ];

    public function entry()
    {
        return $this->belongsTo(UserEntry::class, 'user_entry_id');
    }

    public function film()
    {
        return $this->belongsTo(Film::class, 'film_id', 'idFilm');
    }

    public function comments()
    {
        return $this->morphMany(UserComment::class, 'commentable')->latest();
    }

}


