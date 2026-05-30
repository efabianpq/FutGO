<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchLineup extends Model
{
    protected $fillable = [
        'match_id',
        'team_player_id',
        'team_id',
        'started',
        'minute_in',
        'minute_out',
    ];

    protected function casts(): array
    {
        return [
            'started'    => 'boolean',
            'minute_in'  => 'integer',
            'minute_out' => 'integer',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Minutos efectivamente jugados, dado el match_duration del torneo. */
    public function minutesPlayed(int $matchDuration): int
    {
        $start = (int) $this->minute_in;
        $end   = $this->minute_out !== null ? (int) $this->minute_out : $matchDuration;
        return max(0, $end - $start);
    }
}
