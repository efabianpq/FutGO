<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de auditoría de una validación de credencial (Sesión D).
 * Quién validó, a quién, en qué torneo, con qué resultado y por qué método.
 */
class CredentialValidation extends Model
{
    protected $fillable = [
        'futgo_id',
        'validated_user_id',
        'validated_by_user_id',
        'tournament_id',
        'result',
        'method',
    ];

    /** Jugador validado (puede ser null si el identificador no existía). */
    public function validatedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_user_id');
    }

    /** Árbitro/admin que ejecutó la validación. */
    public function validatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function isHabilitado(): bool
    {
        return $this->result === 'habilitado';
    }
}
