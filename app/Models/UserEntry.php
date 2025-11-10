<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserEntry extends Model
{
    use HasFactory;

    protected $table = 'user_entries';

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'visibility',
        'allow_comments',
        'cover_image',
        'likes_count',
        'status',
        'moderation_notes',
    ];

    protected $casts = [
        'allow_comments' => 'boolean',
        'likes_count' => 'integer',
    ];

    // Relaciones principales
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function films()
    {
        return $this->belongsToMany(Film::class, 'user_entry_films', 'user_entry_id', 'film_id')
                    ->withPivot('order')
                    ->withTimestamps();
    }

    public function comments()
    {
        return $this->morphMany(UserComment::class, 'commentable');
    }



    public function likes()
    {
        return $this->hasMany(UserEntryLike::class, 'user_entry_id');
    }

    // Helpers
    public function isList(): bool
    {
        return $this->type === 'user_list';
    }

    public function isDebate(): bool
    {
        return $this->type === 'user_debate';
    }

    public function isReview(): bool
    {
        return $this->type === 'user_review';
    }
    
}

