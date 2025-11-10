<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    // Nombre explícito de la tabla
    protected $table = 'user_profiles';

    // Campos que pueden asignarse de forma masiva
    protected $fillable = [
        'user_id',
        'avatar',
        'bio',
        'location',
        'website',
        'top_5_films', // películas favoritas de usuario para el perfil personal
        'films_seen',
        'films_rated',
        'films_seen_this_year',
        'lists_created',
        'lists_saved',
        'followers_count',
        'followings_count',
    ];

    // Convierte automáticamente el JSON de top_5_films en array PHP
    protected $casts = [
        'top_5_films' => 'array',
    ];

    //Relación inversa con User-
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


