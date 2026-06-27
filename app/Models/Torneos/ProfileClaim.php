<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reclamo de perfil: solicitud de un usuario registrado para vincular su cuenta
 * a un registro `club_players` que fue creado como 'por_verificar' (jugador sin
 * cuenta anotado por un capitán). Ver migración `create_profile_claims_table`.
 *
 * Estados:
 *  - pending   : esperando la aprobación del capitán del club.
 *  - escalated : el capitán ya no existe / club sin capitán → lo resuelve un admin.
 *  - approved  : vinculado; el club_player heredó su user_id y verification_status.
 *  - rejected  : el capitán/admin lo rechazó; el registro queda sin cambios.
 */
class ProfileClaim extends Model
{
    protected $fillable = [
        'user_id',
        'club_player_id',
        'club_id',
        'document',
        'status',
        'resolved_by_user_id',
        'escalated_at',
        'resolved_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
            'resolved_at'  => 'datetime',
        ];
    }

    /** Usuario que reclama el perfil. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Registro por_verificar reclamado. */
    public function clubPlayer(): BelongsTo
    {
        return $this->belongsTo(ClubPlayer::class);
    }

    /** Club al que pertenece el registro. */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /** Capitán o admin que resolvió el reclamo. */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isEscalated(): bool
    {
        return $this->status === 'escalated';
    }

    /** Reclamo vivo: pendiente o escalado (aún bloquea otros reclamos del mismo registro). */
    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'escalated'], true);
    }

    /** Reclamos vivos (pendientes o escalados). */
    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['pending', 'escalated']);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeEscalated($query)
    {
        return $query->where('status', 'escalated');
    }
}
