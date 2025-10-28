<?php
namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name', 'email', 'password', 'idRol', 'ipLastAccess', 'dateHourLastAccess', 'failedAttempts', 'blocked'
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

    public function role()
    {
        return $this->belongsTo(Rol::class, 'idRol');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'idUser');
    }

    public function individualRates()
    {
        return $this->hasMany(IndividualRate::class, 'idUser');
    }
}

