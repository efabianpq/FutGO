<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStat extends Model
{
    protected $fillable = [
        'tournament_id',
        'team_player_id',
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'minutes_played',
        'matches_played',
        'wins',
        'draws',
        'losses',
        'clean_sheets',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_calculated_at' => 'datetime',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function teamPlayer(): BelongsTo
    {
        return $this->belongsTo(TeamPlayer::class);
    }
}
