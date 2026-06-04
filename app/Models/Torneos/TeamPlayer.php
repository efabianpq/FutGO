<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TeamPlayer extends Model
{
    protected $fillable = [
        'team_id',
        'user_id',
        'is_captain',
        'full_name',
        'document',
        'verification_status',
        'jersey_number',
        'position',
        'status',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchEvents(): HasMany
    {
        return $this->hasMany(MatchEvent::class);
    }

    public function playerStat(): HasOne
    {
        return $this->hasOne(PlayerStat::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /** ¿Este jugador es el capitán del equipo? (marcador a nivel de membresía) */
    public function isCaptain(): bool
    {
        return (bool) $this->is_captain;
    }

    /** ¿Es un jugador con cuenta en la app? */
    public function isRegistered(): bool
    {
        return $this->verification_status === 'registrado';
    }

    /** ¿Es un jugador real sin cuenta, pendiente de verificación? */
    public function isPorVerificar(): bool
    {
        return $this->verification_status === 'por_verificar';
    }

    /** Nombre a mostrar: el del usuario si está registrado, si no el full_name cargado. */
    public function displayName(): string
    {
        return $this->user?->name ?? $this->full_name ?? '—';
    }

    /** Solo capitanes. */
    public function scopeCaptains(Builder $query): Builder
    {
        return $query->where('is_captain', true);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
