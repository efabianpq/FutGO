<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FutGO Social — ReliabilityEvent: evento que alimenta la confiabilidad de un
 * usuario o club (no-shows, cancelaciones tardías, respuestas rápidas,
 * calificaciones). Fuente reconstruible del cache `reliability_scores`.
 */
class ReliabilityEvent extends Model
{
    /** Tipos de evento (impacto en el score lo define el servicio). */
    public const TYPE_NO_SHOW               = 'no_show';
    public const TYPE_CANCELACION_TARDIA    = 'cancelacion_tardia';
    public const TYPE_RESPUESTA_RAPIDA      = 'respuesta_rapida';
    public const TYPE_CALIFICACION_POSITIVA = 'calificacion_positiva';
    public const TYPE_CALIFICACION_NEGATIVA = 'calificacion_negativa';

    public const TYPES = [
        self::TYPE_NO_SHOW,
        self::TYPE_CANCELACION_TARDIA,
        self::TYPE_RESPUESTA_RAPIDA,
        self::TYPE_CALIFICACION_POSITIVA,
        self::TYPE_CALIFICACION_NEGATIVA,
    ];

    /** Tipos que penalizan la confiabilidad. */
    public const NEGATIVE_TYPES = [
        self::TYPE_NO_SHOW,
        self::TYPE_CANCELACION_TARDIA,
        self::TYPE_CALIFICACION_NEGATIVA,
    ];

    protected $fillable = [
        'subject_type',
        'subject_id',
        'type',
        'opportunity_id',
        'friendly_match_id',
        'meta',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'meta'        => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    // --- Relaciones ---

    /** Sujeto del evento (User o Club). */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function friendlyMatch(): BelongsTo
    {
        return $this->belongsTo(FriendlyMatch::class);
    }

    // --- Helpers semánticos ---

    public function isNoShow(): bool
    {
        return $this->type === self::TYPE_NO_SHOW;
    }

    public function isCancelacionTardia(): bool
    {
        return $this->type === self::TYPE_CANCELACION_TARDIA;
    }

    public function isRespuestaRapida(): bool
    {
        return $this->type === self::TYPE_RESPUESTA_RAPIDA;
    }

    /** ¿El evento penaliza la confiabilidad? */
    public function isNegative(): bool
    {
        return in_array($this->type, self::NEGATIVE_TYPES, true);
    }

    // --- Scopes ---

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeNoShows($query)
    {
        return $query->where('type', self::TYPE_NO_SHOW);
    }

    /** Eventos ocurridos en los últimos N días (regla de pausa por no-shows). */
    public function scopeWithinDays($query, int $days)
    {
        return $query->where('occurred_at', '>=', now()->subDays($days));
    }
}
