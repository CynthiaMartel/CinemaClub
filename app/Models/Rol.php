<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Rol extends Model
{
    protected $table = 'rol';
    protected $fillable = ['rolType'];
    
    public function users()
    {
        return $this->hasMany(User::class, 'idRol');
    }
}

