<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchEvent extends Model
{
    protected $fillable = [
        'match_id',
        'team_player_id',
        'type',
        'minute',
        'notes',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class);
    }

    public function isGoal(): bool
    {
        return $this->type === 'goal';
    }

    public function isOwnGoal(): bool
    {
        return $this->type === 'own_goal';
    }

    public function isYellowCard(): bool
    {
        return $this->type === 'yellow_card';
    }

    public function isRedCard(): bool
    {
        return $this->type === 'red_card';
    }
}
