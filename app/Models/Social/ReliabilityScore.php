<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FutGO Social — ReliabilityScore: score de confiabilidad 0-100 cacheado por
 * sujeto (user|club). Derivado de `reliability_events`, reconstruible — mismo
 * patrón que FairPlayScore. `is_paused` materializa la pausa automática por
 * acumulación de no-shows.
 */
class ReliabilityScore extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'score',
        'no_shows',
        'late_cancellations',
        'fast_responses',
        'positive_ratings',
        'negative_ratings',
        'is_paused',
        'paused_at',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score'              => 'integer',
            'no_shows'           => 'integer',
            'late_cancellations' => 'integer',
            'fast_responses'     => 'integer',
            'positive_ratings'   => 'integer',
            'negative_ratings'   => 'integer',
            'is_paused'          => 'boolean',
            'paused_at'          => 'datetime',
            'calculated_at'      => 'datetime',
        ];
    }

    /** Sujeto del score (User o Club). */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** ¿La disponibilidad está pausada (requiere reactivación manual)? */
    public function isPaused(): bool
    {
        return (bool) $this->is_paused;
    }

    /** ¿El sujeto puede participar en el matching? (score declarado y no pausado). */
    public function isAvailableForMatching(): bool
    {
        return ! $this->isPaused();
    }

    public function scopePlayers($query)
    {
        return $query->where('subject_type', 'user');
    }

    public function scopeClubs($query)
    {
        return $query->where('subject_type', 'club');
    }

    public function scopePaused($query)
    {
        return $query->where('is_paused', true);
    }
}
