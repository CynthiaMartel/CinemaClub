<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsItem extends Model
{
    protected $fillable = [
        'source_id',
        'title',
        'original_url',
        'raw_content',
        'ai_summary',
        'ai_tags',
        'ai_relevance_score',
        'ai_suggested_title',
        'ai_category',
        'ai_canarian_entities',
        'status',
        'published_post_id',
        'found_at',
        'processed_at',
    ];

    protected $casts = [
        'ai_tags'             => 'array',
        'ai_canarian_entities'=> 'array',
        'found_at'            => 'datetime',
        'processed_at'        => 'datetime',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /** Items con alta relevancia (>= 7) */
    public function scopeHighRelevance($query, int $min = 7)
    {
        return $query->where('ai_relevance_score', '>=', $min);
    }

    /** Items que aún no han sido procesados por la IA */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('ai_summary');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isProcessed(): bool
    {
        return ! is_null($this->ai_summary);
    }

    public function relevanceBadgeColor(): string
    {
        $score = $this->ai_relevance_score ?? 0;
        if ($score >= 8) return 'green';
        if ($score >= 5) return 'yellow';
        return 'slate';
    }
}
