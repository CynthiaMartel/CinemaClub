<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSavedList extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_entry_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function entry()
    {
        return $this->belongsTo(UserEntry::class, 'user_entry_id');
    }
}

