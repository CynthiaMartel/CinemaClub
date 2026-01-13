<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'idRol',
        'ipLastAccess',
        'dateHourLastAccess',
        'failedAttempts',
        'blocked'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'blocked' => 'boolean',
        'dateHourLastAccess' => 'datetime',
    ];

    // RELACIONES ROLE, POST

    public function role()
    {
        return $this->belongsTo(Rol::class, 'idRol');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'idUser');
    }

    // RELACIONES SOCIALMEDIA
    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function filmActions()
    {
        return $this->hasMany(UserFilmActions::class);
    }

    // Seguidores: usuarios que siguen al user (FOLLOWERS)
    public function followers()
    {
        return $this->hasMany(UserFriend::class, 'followed_id')
                    ->where('status', 'accepted');
    }

    // Seguidos: usuarios que sigue el user (FOLLOWINGS)
    public function followings()
    {
        return $this->hasMany(UserFriend::class, 'follower_id')
                    ->where('status', 'accepted');
    }

    // Usuarios que he bloqueado
    public function blockedUsers()
    {
        return $this->hasMany(UserFriend::class, 'follower_id')
                    ->where('status', 'blocked');
    }

    // MÉTODOS DE ROL 

    // Verificar si el usuario tiene rol de Admin (en BD Admin tiene id = 1)
    public function isAdmin()
    {
        return $this->role
            && (
                strtolower($this->role->rolType) === 'admin'
                || (int) $this->idRol === 1
            );
    }


    // Verifica si el usuario tiene rol de Editor (en BD Editor tiene id=2 )
    public function isEditor()
    {
        return $this->role
            && (
                strtolower($this->role->rolType) === 'editor'
                || (int) $this->idRol === 2
            );
    }

    // Verifica si el usuario tiene rol de User (usuario "regular". En BD User tiene id=3)
        public function isUser()
    {
        return $this->role
            && (
                strtolower($this->role->rolType) === 'user'
                || (int) $this->idRol === 3
            );
    }
    // Verifica si el usuario es Admin o Editor (para editar, crear, borrar posts, etc)
    public function isAdminOrEditor()
    {
        return $this->isAdmin() || $this->isEditor();
    }
}








