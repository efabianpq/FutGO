<?php

namespace App\Models\Social;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FutGO Social — Fase 1 · Sesión S1-E · evento del Feed del sistema.
 *
 * Un registro por evento (nunca uno por usuario): la relevancia se calcula al
 * leer (ver FeedService). `actor` y `subject` son las dos entidades que el evento
 * conecta — seguir a cualquiera lo vuelve relevante. `city` + `required_level`
 * son la regla de distribución por ubicación/nivel. `payload` lleva todo lo
 * necesario para renderizar la tarjeta sin joins.
 */
class FeedEvent extends Model
{
    /** Tipos de evento (STRING extensible sin migración). */
    public const TYPE_OPORTUNIDAD_PUBLICADA = 'oportunidad_publicada';
    public const TYPE_OPORTUNIDAD_ACEPTADA  = 'oportunidad_aceptada';
    public const TYPE_AMISTOSO_CONFIRMADO   = 'amistoso_confirmado';
    public const TYPE_RESULTADO_AMISTOSO    = 'resultado_amistoso';
    public const TYPE_RESULTADO_TORNEO      = 'resultado_torneo';
    public const TYPE_LOGRO_DESBLOQUEADO    = 'logro_desbloqueado';
    public const TYPE_OPORTUNIDAD_RESPONDIDA = 'oportunidad_respondida';

    /** Etiquetas legibles (voseo) para la UI. */
    public const TYPE_LABELS = [
        self::TYPE_OPORTUNIDAD_PUBLICADA  => 'Nueva oportunidad',
        self::TYPE_OPORTUNIDAD_ACEPTADA   => 'Oportunidad aceptada',
        self::TYPE_OPORTUNIDAD_RESPONDIDA => 'Respuesta a oportunidad',
        self::TYPE_AMISTOSO_CONFIRMADO    => 'Amistoso confirmado',
        self::TYPE_RESULTADO_AMISTOSO     => 'Resultado de amistoso',
        self::TYPE_RESULTADO_TORNEO       => 'Resultado de torneo',
        self::TYPE_LOGRO_DESBLOQUEADO     => 'Logro desbloqueado',
    ];

    protected $fillable = [
        'type',
        'actor_type',
        'actor_id',
        'subject_type',
        'subject_id',
        'city',
        'required_level',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    // --- Relaciones polimórficas ---

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    // --- Helpers de presentación ---

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    /** Acceso seguro a una clave del payload. */
    public function meta(string $key, mixed $default = null): mixed
    {
        return data_get($this->payload, $key, $default);
    }
}
