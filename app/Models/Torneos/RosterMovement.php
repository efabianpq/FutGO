<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento de plantilla durante el torneo (baja / cambio de equipo). */
class RosterMovement extends Model
{
    protected $fillable = [
        'tournament_id',
        'team_player_id',
        'user_id',
        'player_name',
        'type',
        'from_team_id',
        'to_team_id',
        'acted_by_user_id',
        'notes',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }

    public function actedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acted_by_user_id');
    }
}
