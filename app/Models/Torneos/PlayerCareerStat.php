<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Acumulado histórico del jugador (hoja de vida deportiva).
 * Una fila por usuario; derivado persistente de player_stats across torneos.
 */
class PlayerCareerStat extends Model
{
    protected $fillable = [
        'user_id',
        'matches_played',
        'goals',
        'assists',
        'yellow_cards',
        'red_cards',
        'minutes_played',
        'wins',
        'draws',
        'losses',
        'clean_sheets',
        'mvps',
        'tournaments_count',
        'teams_count',
        'last_consolidated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_consolidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
