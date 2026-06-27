<?php

namespace App\Models\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FutGO Social — Follow: un usuario sigue una entidad (club, jugador, torneo).
 * Tabla polimórfica única; alimenta el Feed con la actividad de lo seguido.
 */
class Follow extends Model
{
    protected $fillable = [
        'user_id',
        'followable_type',
        'followable_id',
    ];

    /** El usuario que sigue. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** La entidad seguida (Club, User o Tournament). */
    public function followable(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Helpers semánticos ---

    public function isFollowingClub(): bool
    {
        return $this->followable_type === 'club';
    }

    public function isFollowingPlayer(): bool
    {
        return $this->followable_type === 'user';
    }

    public function isFollowingTournament(): bool
    {
        return $this->followable_type === 'tournament';
    }

    // --- Scopes ---

    public function scopeOfType($query, string $followableType)
    {
        return $query->where('followable_type', $followableType);
    }
}
