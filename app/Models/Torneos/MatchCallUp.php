<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convocatoria previa: planeación de asistencia antes del partido.
 * No afecta estadísticas (eso es match_lineups).
 */
class MatchCallUp extends Model
{
    protected $fillable = [
        'match_id',
        'team_player_id',
        'team_id',
        'status',
        'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
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

    public function isConvocado(): bool { return $this->status === 'convocado'; }
    public function isConfirmado(): bool { return $this->status === 'confirmado'; }
    public function isDeclinado(): bool { return $this->status === 'declinado'; }
}
