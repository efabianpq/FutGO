<?php

namespace App\Models\Torneos;

use App\Models\Concerns\HasPlayLevel;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Club: identidad PERMANENTE de un equipo que persiste entre torneos.
 * Cada `Team` es la inscripción de este club en un torneo concreto.
 */
class Club extends Model
{
    use HasPlayLevel;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'shield_url',
        'created_by_user_id',
        'captain_user_id',
        'status',
        'play_level',
        'level_suggestion_dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'level_suggestion_dismissed_at' => 'datetime',
        ];
    }

    /** ¿El capitán ya ignoró la sugerencia de recategorización de nivel? */
    public function dismissedLevelSuggestion(): bool
    {
        return $this->level_suggestion_dismissed_at !== null;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** Capitán permanente del equipo. */
    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_user_id');
    }

    /** Plantilla permanente del equipo (transversal a torneos). */
    public function players(): HasMany
    {
        return $this->hasMany(ClubPlayer::class);
    }

    /** Todas las participaciones del club (una por torneo). */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** Cantidad de torneos en los que ha participado. */
    public function tournamentsCount(): int
    {
        return $this->teams()->distinct('tournament_id')->count('tournament_id');
    }

    public function isCaptainedBy(?User $user): bool
    {
        return $user !== null && $this->captain_user_id === $user->id;
    }

    /** Club validado: creado por su propio capitán (identidad confirmada). */
    public function isValidado(): bool
    {
        return $this->status === 'validado';
    }

    /** Club por validar: creado por un admin de torneo, sin capitán confirmado. */
    public function isPorValidar(): bool
    {
        return $this->status === 'por_validar';
    }

    /** Clubs creados por capitanes (con identidad confirmada). */
    public function scopeValidados($query)
    {
        return $query->where('status', 'validado');
    }

    /** Escudo a mostrar. */
    public function shieldUrl(): ?string
    {
        return $this->shield_url;
    }

    /**
     * ¿El equipo participa actualmente en algún torneo "activo" (open o in_progress)?
     * Bloquea cambios de nombre/logo mientras compite.
     */
    public function isInActiveTournament(): bool
    {
        return $this->teams()
            ->whereHas('tournament', fn ($q) => $q->whereIn('status', ['open', 'in_progress']))
            ->exists();
    }

    // --- FutGO Social (Fase 1) ---

    /** Oportunidades publicadas por este club (BUSCAR_RIVAL, BUSCAR_JUGADOR, etc.). */
    public function opportunities(): HasMany
    {
        return $this->hasMany(\App\Models\Social\Opportunity::class);
    }

    /** Respuestas que este club dio a oportunidades de otros. */
    public function opportunityResponses(): HasMany
    {
        return $this->hasMany(\App\Models\Social\OpportunityResponse::class);
    }

    /** Amistosos jugados como local. */
    public function homeFriendlyMatches(): HasMany
    {
        return $this->hasMany(\App\Models\Social\FriendlyMatch::class, 'home_club_id');
    }

    /** Amistosos jugados como visitante. */
    public function awayFriendlyMatches(): HasMany
    {
        return $this->hasMany(\App\Models\Social\FriendlyMatch::class, 'away_club_id');
    }

    /** Seguidores del club (usuarios que lo siguen). */
    public function followers(): MorphMany
    {
        return $this->morphMany(\App\Models\Social\Follow::class, 'followable');
    }

    /** Eventos de confiabilidad del club. */
    public function reliabilityEvents(): MorphMany
    {
        return $this->morphMany(\App\Models\Social\ReliabilityEvent::class, 'subject');
    }

    /** Score de confiabilidad cacheado del club. */
    public function reliabilityScore(): MorphOne
    {
        return $this->morphOne(\App\Models\Social\ReliabilityScore::class, 'subject');
    }
}
