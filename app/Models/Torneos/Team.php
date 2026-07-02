<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Team extends Model
{
    protected $fillable = [
        'tournament_id',
        'club_id',
        'captain_user_id',
        'name',
        'slug',
        'color',
        'shield_url',
        'status',
    ];

    protected static function booted(): void
    {
        // Slug único derivado del nombre si no se pasó explícitamente (URLs sin id numérico).
        static::creating(function (Team $team) {
            if (! empty($team->slug)) {
                return;
            }

            $base = Str::slug($team->name) ?: 'equipo';
            $slug = $base;
            $i = 1;
            while (static::where('slug', $slug)->exists()) {
                $i++;
                $slug = $base . '-' . $i;
            }
            $team->slug = $slug;
        });
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /** Club permanente al que pertenece esta inscripción. */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** Escudo a mostrar: el de la inscripción si existe, si no el del club. */
    public function shieldUrl(): ?string
    {
        return $this->shield_url ?? $this->club?->shield_url;
    }

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_user_id');
    }

    public function players(): HasMany
    {
        return $this->hasMany(TeamPlayer::class);
    }

    /** Membresía del capitán dentro del equipo (team_player con is_captain=true). */
    public function captainPlayer(): HasOne
    {
        return $this->hasOne(TeamPlayer::class)->where('is_captain', true);
    }

    /**
     * ¿El usuario dado es capitán de este equipo?
     * La condición se deriva del equipo, nunca de un rol global en users:
     * coincide con captain_user_id (puntero denormalizado) y con la membresía
     * marcada is_captain.
     */
    public function isCaptainedBy(?User $user): bool
    {
        return $user !== null && $this->captain_user_id === $user->id;
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    public function playerStats(): HasMany
    {
        return $this->hasMany(PlayerStat::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
