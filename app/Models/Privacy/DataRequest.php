<?php

namespace App\Models\Privacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centro de Privacidad · Solicitud de datos personales (habeas data).
 *
 * type=export → portabilidad; type=delete → derecho al olvido con periodo de gracia.
 */
class DataRequest extends Model
{
    public const TYPE_EXPORT = 'export';
    public const TYPE_DELETE = 'delete';

    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY      = 'ready';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_CANCELLED  = 'cancelled';

    protected $fillable = [
        'user_id',
        'type',
        'status',
        'verification_code',
        'verified_at',
        'file_path',
        'requested_ip',
        'executes_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at'  => 'datetime',
            'executes_at'  => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'verification_code',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** ¿Terminó el periodo de gracia y está listo para ejecutarse? */
    public function isDueForExecution(): bool
    {
        return $this->type === self::TYPE_DELETE
            && $this->status === self::STATUS_PROCESSING
            && $this->executes_at !== null
            && $this->executes_at->isPast();
    }

    // --- Scopes ---

    public function scopeDeletes($query)
    {
        return $query->where('type', self::TYPE_DELETE);
    }

    public function scopeExports($query)
    {
        return $query->where('type', self::TYPE_EXPORT);
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
