<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class NewsSource extends Model
{
    protected $fillable = [
        'name',
        'url',
        'type',
        'check_interval_hours',
        'last_checked_at',
        'is_active',
        'selector_config',
        'failed_attempts',
        'needs_review',
        'last_error',
    ];

    protected $casts = [
        'is_active'       => 'boolean',
        'needs_review'    => 'boolean',
        'last_checked_at' => 'datetime',
        'selector_config' => 'array',
    ];

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function newsItems(): HasMany
    {
        return $this->hasMany(NewsItem::class, 'source_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /** Fuentes activas que ya están vencidas para un nuevo rastreo */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDueForCheck($query)
    {
        return $query->where('is_active', true)
            ->where('needs_review', false)
            ->where(function ($q) {
                $q->whereNull('last_checked_at')
                  ->orWhereRaw('last_checked_at < DATE_SUB(NOW(), INTERVAL check_interval_hours HOUR)');
            });
    }

    public function scopeNeedsReview($query)
    {
        return $query->where('needs_review', true);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isDue(): bool
    {
        if (is_null($this->last_checked_at)) {
            return true;
        }
        return $this->last_checked_at->addHours($this->check_interval_hours)->isPast();
    }

    public function itemsFoundToday(): int
    {
        return $this->newsItems()
            ->whereDate('found_at', Carbon::today())
            ->count();
    }

    /** Registra un error y marca como "Requiere revisión" al 3er fallo */
    public function recordFailure(string $error): void
    {
        $attempts = $this->failed_attempts + 1;
        $this->update([
            'failed_attempts' => $attempts,
            'last_error'      => mb_substr($error, 0, 500),
            'needs_review'    => $attempts >= 3,
            'last_checked_at' => now(),
        ]);
    }

    /** Resetea el contador de errores tras un rastreo exitoso */
    public function recordSuccess(): void
    {
        $this->update([
            'failed_attempts' => 0,
            'needs_review'    => false,
            'last_error'      => null,
            'last_checked_at' => now(),
        ]);
    }
}
