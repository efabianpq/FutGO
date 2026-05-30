<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    protected $fillable = [
        'phase_id',
        'group_id',
        'team_id',
        'played',
        'won',
        'drawn',
        'lost',
        'goals_for',
        'goals_against',
        'goal_difference',
        'points',
        'position',
        'last_calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'last_calculated_at' => 'datetime',
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

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
