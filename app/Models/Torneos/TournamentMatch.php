<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentMatch extends Model
{
    protected $table = 'tournament_matches';

    protected $fillable = [
        'phase_id',
        'group_id',
        'home_team_id',
        'away_team_id',
        'winner_team_id',
        'home_score',
        'away_score',
        'status',
        'scheduled_at',
        'venue',
        'match_number',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
        ];
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }

    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(MatchEvent::class, 'match_id');
    }

    public function lineups(): HasMany
    {
        return $this->hasMany(MatchLineup::class, 'match_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function isPostponed(): bool
    {
        return $this->status === 'postponed';
    }

    public function hasResult(): bool
    {
        return $this->home_score !== null && $this->away_score !== null;
    }
}
