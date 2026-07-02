<?php

namespace App\Models\Torneos;

use App\Models\Concerns\HasHashedDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Miembro de la plantilla PERMANENTE de un equipo (club).
 * Transversal: no depende de ningún torneo. Al enrolar el equipo en un torneo,
 * se copia a team_players como snapshot de esa participación.
 */
class ClubPlayer extends Model
{
    use HasHashedDocument;

    protected $fillable = [
        'club_id',
        'user_id',
        'is_captain',
        'full_name',
        'document',
        'document_hash',
        'verification_status',
        'jersey_number',
        'position',
        'status',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'document'   => 'encrypted',
    ];

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Reclamos de perfil sobre este registro (vinculación de cuenta a 'por_verificar'). */
    public function profileClaims(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProfileClaim::class);
    }

    public function isCaptain(): bool
    {
        return (bool) $this->is_captain;
    }

    public function isRegistered(): bool
    {
        return $this->verification_status === 'registrado';
    }

    public function isPorVerificar(): bool
    {
        return $this->verification_status === 'por_verificar';
    }

    public function displayName(): string
    {
        return $this->user?->name ?? $this->full_name ?? '—';
    }
}
