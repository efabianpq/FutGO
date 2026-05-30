<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentGroup extends Model
{
    protected $table = 'tournament_groups';

    protected $fillable = [
        'phase_id',
        'name',
        'order',
    ];

    public function phase(): BelongsTo
    {
        return $this->belongsTo(TournamentPhase::class, 'phase_id');
    }

    public function teams(): BelongsToMany
    {
        // La columna pivote real es group_id (no la inferida tournament_group_id).
        return $this->belongsToMany(Team::class, 'group_teams', 'group_id', 'team_id')
                    ->withTimestamps();
    }

    public function groupTeams(): HasMany
    {
        return $this->hasMany(GroupTeam::class, 'group_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'group_id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class, 'group_id');
    }
}
