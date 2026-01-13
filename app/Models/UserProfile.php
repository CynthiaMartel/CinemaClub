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
        'top_films',
    ];

    protected $casts = [
        'top_films' => 'array',
    ];

     //Relación inversa con User-
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

   


