<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'user_profiles';

    
    protected $fillable = [
        'user_id',
        'avatar',
        'bio',
        'location',
        'website',
        'top_films',
        'followers_count',
        'following_count',
    ];

    protected $casts = [
        'top_films' => 'array',
        'followers_count' => 'integer',
        'following_count' => 'integer',
    ];

     //
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

   


