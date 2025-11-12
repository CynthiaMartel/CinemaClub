<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserFriend extends Model
{
    use HasFactory;

    protected $table = 'user_friends';

    protected $fillable = [
        'follower_id',
        'followed_id',
        'status',
    ];

    // Usuario que sigue el user (FOLLOWERS)
     
    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    //Usuario que es seguido (FOLLOWED)
    
    public function followed()
    {
        return $this->belongsTo(User::class, 'followed_id');
    }

    // Scope para filtrar solo relaciones de seguimiento (solo marcados como aceptados, excluyendo bloqueos)
    public function scopeFollowings($query)
    {
        return $query->where('status', 'accepted');
    }

    // Scope para filtrar usuarios bloqueados por el user
    
    public function scopeBlocked($query)
    {
        return $query->where('status', 'blocked');
    }
}

