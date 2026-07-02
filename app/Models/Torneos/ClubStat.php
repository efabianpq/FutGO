<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stats de torneo agregadas de un club, cacheadas (deuda #3). Reconstruible
 * vía ClubStatsService::refreshForClub()/rebuild().
 */
class ClubStat extends Model
{
    protected $fillable = [
        'club_id', 'played', 'won', 'drawn', 'lost',
        'goals_for', 'goals_against', 'top_scorers', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'played'        => 'integer',
            'won'           => 'integer',
            'drawn'         => 'integer',
            'lost'          => 'integer',
            'goals_for'     => 'integer',
            'goals_against' => 'integer',
            'top_scorers'   => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
