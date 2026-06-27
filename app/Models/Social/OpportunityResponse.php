<?php

namespace App\Models\Social;

use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FutGO Social — OpportunityResponse: respuesta a una oportunidad publicada.
 *
 * Una oportunidad tiene muchas respuestas pero solo una `aceptada` (garantizado
 * por el servicio de aceptación). `message` es el mensaje estructurado del MVP.
 */
class OpportunityResponse extends Model
{
    public const STATUS_PENDIENTE       = 'pendiente';
    public const STATUS_ACEPTADA        = 'aceptada';
    public const STATUS_RECHAZADA       = 'rechazada';
    public const STATUS_CONTRAPROPUESTA = 'contrapropuesta';

    public const STATUSES = [
        self::STATUS_PENDIENTE,
        self::STATUS_ACEPTADA,
        self::STATUS_RECHAZADA,
        self::STATUS_CONTRAPROPUESTA,
    ];

    protected $fillable = [
        'opportunity_id',
        'user_id',
        'club_id',
        'message',
        'status',
    ];

    /** Default alineado con la migración. */
    protected $attributes = [
        'status' => self::STATUS_PENDIENTE,
    ];

    // --- Relaciones ---

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    // --- Helpers semánticos de estado ---

    public function isPendiente(): bool
    {
        return $this->status === self::STATUS_PENDIENTE;
    }

    public function isAceptada(): bool
    {
        return $this->status === self::STATUS_ACEPTADA;
    }

    public function isRechazada(): bool
    {
        return $this->status === self::STATUS_RECHAZADA;
    }

    public function isContrapropuesta(): bool
    {
        return $this->status === self::STATUS_CONTRAPROPUESTA;
    }

    public function scopeAceptadas($query)
    {
        return $query->where('status', self::STATUS_ACEPTADA);
    }

    public function scopePendientes($query)
    {
        return $query->where('status', self::STATUS_PENDIENTE);
    }
}
