<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Registro auditable de un sorteo de desempate en standings (Sesión F). */
class StandingDraw extends Model
{
    protected $fillable = ['phase_id', 'group_id', 'team_id', 'seed', 'draw_position'];

    protected function casts(): array
    {
        return [
            'seed'          => 'integer',
            'draw_position' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(TournamentGroup::class, 'group_id');
    }
}
