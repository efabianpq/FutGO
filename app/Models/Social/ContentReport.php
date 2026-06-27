<?php

namespace App\Models\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FutGO Social — ContentReport: reporte de contenido o usuario abusivo.
 * Mínimo viable de moderación, requerido desde el lanzamiento porque las
 * oportunidades y mensajes son texto libre.
 */
class ContentReport extends Model
{
    public const STATUS_PENDIENTE = 'pendiente';
    public const STATUS_REVISADO  = 'revisado';
    public const STATUS_RESUELTO  = 'resuelto';

    public const STATUSES = [
        self::STATUS_PENDIENTE,
        self::STATUS_REVISADO,
        self::STATUS_RESUELTO,
    ];

    public const ACTION_DISMISSED = 'dismissed';
    public const ACTION_HIDDEN    = 'hidden';
    public const ACTION_SUSPENDED = 'suspended';

    public const ACTIONS = [
        self::ACTION_DISMISSED,
        self::ACTION_HIDDEN,
        self::ACTION_SUSPENDED,
    ];

    protected $fillable = [
        'reporter_user_id',
        'reportable_type',
        'reportable_id',
        'reason',
        'details',
        'status',
        'admin_notes',
        'reviewed_by_user_id',
        'reviewed_at',
        'resolution_action',
    ];

    /** Default alineado con la migración. */
    protected $attributes = [
        'status' => self::STATUS_PENDIENTE,
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /** Etiqueta legible para la acción de resolución. */
    public function resolutionActionLabel(): string
    {
        return match ($this->resolution_action) {
            self::ACTION_DISMISSED => 'Desestimado',
            self::ACTION_HIDDEN    => 'Contenido oculto',
            self::ACTION_SUSPENDED => 'Usuario suspendido',
            default                => '—',
        };
    }

    // --- Relaciones ---

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    /** Entidad reportada (Opportunity, Message, User o Club). */
    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Helpers semánticos de estado ---

    public function isPendiente(): bool
    {
        return $this->status === self::STATUS_PENDIENTE;
    }

    public function isRevisado(): bool
    {
        return $this->status === self::STATUS_REVISADO;
    }

    public function isResuelto(): bool
    {
        return $this->status === self::STATUS_RESUELTO;
    }

    // --- Scopes ---

    public function scopePendientes($query)
    {
        return $query->where('status', self::STATUS_PENDIENTE);
    }

    public function scopeResueltos($query)
    {
        return $query->where('status', self::STATUS_RESUELTO);
    }
}
