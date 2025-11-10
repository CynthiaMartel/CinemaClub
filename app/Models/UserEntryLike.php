<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEntryLike extends Model
{
    use HasFactory;

    protected $table = 'user_entry_likes';

    protected $fillable = [
        'user_entry_id',
        'user_id',
    ];

    public function entry()
    {
        return $this->belongsTo(UserEntry::class, 'user_entry_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}


