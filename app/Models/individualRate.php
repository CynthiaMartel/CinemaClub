<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndividualRate extends Model
{
    protected $table = 'individual_rate';
    protected $fillable = ['rate','idUser','idFilm'];

    public function user()
    {
        return $this->belongsTo(User::class, 'idUser');
    }

    public function film()
    {
        return $this->belongsTo(Film::class, 'idFilm', 'idFilm');
    }
}

