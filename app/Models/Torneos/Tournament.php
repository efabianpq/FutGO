<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    /** Estadísticas que el admin puede elegir trackear desde el formulario. */
    public const SELECTABLE_STATS = ['goals', 'assists', 'yellow_cards', 'red_cards', 'minutes_played'];

    /** Estadísticas derivadas siempre activas por defecto. */
    public const DEFAULT_ON_STATS = ['wins', 'draws', 'losses', 'clean_sheets'];

    /** Criterios de desempate válidos para la tabla de posiciones. */
    public const TIEBREAKER_OPTIONS = ['goal_difference', 'goals_for', 'head_to_head', 'fair_play', 'drawing'];

    protected $fillable = [
        'name',
        'slug',
        'sport',
        'status',
        'format',
        'groups_count',
        'teams_per_group',
        'classifies_per_group',
        'third_place_match',
        'stats_config',
        'created_by_user_id',
        'registration_opens_at',
        'registration_closes_at',
        'starts_at',
        'ends_at',
        'description',
        // Visibilidad y categoría
        'visibility',
        'category',
        'city',
        'venue',
        // Equipos
        'max_teams',
        // Sistema de puntuación
        'points_win',
        'points_draw',
        'points_loss',
        // Desempates
        'tiebreaker_order',
        'knockout_tiebreak',
        // Configuración deportiva
        'min_players_per_team',
        'max_players_per_team',
        'match_duration',
        'max_substitutions',
        // Información adicional
        'registration_fee',
        'prize_description',
        'rules',
        'logo_url',
        'banner_url',
    ];

    protected function casts(): array
    {
        return [
            'third_place_match'       => 'boolean',
            'stats_config'            => 'array',
            'tiebreaker_order'        => 'array',
            'registration_opens_at'   => 'datetime',
            'registration_closes_at'  => 'datetime',
            'starts_at'               => 'datetime',
            'ends_at'                 => 'datetime',
        ];
    }

    /** Orden de desempate por defecto sugerido. */
    public function getDefaultTiebreakerOrder(): array
    {
        return ['goal_difference', 'goals_for', 'head_to_head', 'fair_play', 'drawing'];
    }

    public function isPublic(): bool
    {
        return $this->visibility === 'public';
    }

    public function isPrivate(): bool
    {
        return $this->visibility === 'private';
    }

    /**
     * Mapa asociativo de estadísticas habilitadas (stat => bool).
     * Las seleccionables se leen de stats_config; las derivadas
     * (wins, draws, losses, clean_sheets) están activas por defecto.
     */
    public function getStatsConfig(): array
    {
        $stored = $this->stats_config ?? [];
        $config = [];

        foreach (self::SELECTABLE_STATS as $stat) {
            // Sin configuración previa, todas las seleccionables quedan activas.
            $config[$stat] = $stored === [] ? true : in_array($stat, $stored, true);
        }

        foreach (self::DEFAULT_ON_STATS as $stat) {
            $config[$stat] = true;
        }

        return $config;
    }

    /** Scope: solo torneos públicos (para listados públicos del Prompt 8 en adelante). */
    public function scopePublic($query)
    {
        return $query->where('visibility', 'public');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tournament_admins')
                    ->withTimestamps();
    }

    public function tournamentAdmins(): HasMany
    {
        return $this->hasMany(TournamentAdmin::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function phases(): HasMany
    {
        return $this->hasMany(TournamentPhase::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TournamentInvitation::class);
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStat::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
