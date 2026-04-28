<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class CinemaEvent extends Model
{
    protected $fillable = [
        'source_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'event_type',
        'venue',
        'island',
        'ticket_url',
        'image_url',
        'source_url',
        'raw_text',
        'ai_confidence',
        'status',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'ai_confidence'  => 'decimal:2',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function source(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class, 'source_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /** Eventos confirmados que aún no han terminado */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'confirmed')
                     ->where(function ($q) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', Carbon::today());
                     });
    }

    /** Eventos que han comenzado y aún no han terminado */
    public function scopeOngoing($query)
    {
        $today = Carbon::today();
        return $query->where('start_date', '<=', $today)
                     ->where(function ($q) use ($today) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', $today);
                     });
    }

    /** Eventos por isla */
    public function scopeByIsland($query, string $island)
    {
        return $query->where(function ($q) use ($island) {
            $q->where('island', $island)->orWhere('island', 'ALL');
        });
    }

    /** Eventos en un rango de fechas */
    public function scopeInRange($query, string $from, string $to)
    {
        return $query->where('start_date', '<=', $to)
                     ->where(function ($q) use ($from) {
                         $q->whereNull('end_date')
                           ->orWhere('end_date', '>=', $from);
                     });
    }

    /** Items que aún no han sido procesados por la IA */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('ai_confidence');
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('status', 'needs_review');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /** Un festival tiene rango de fechas; una proyección es de un solo día */
    public function isMultiDay(): bool
    {
        return $this->end_date && $this->end_date->gt($this->start_date);
    }

    public function durationDays(): int
    {
        if (! $this->end_date) {
            return 1;
        }
        return $this->start_date->diffInDays($this->end_date) + 1;
    }
}
