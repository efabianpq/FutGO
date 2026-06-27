<?php

namespace App\Models\Social;

use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * FutGO Social — Opportunity: entidad central de Conexiones.
 *
 * "Alguien publica que necesita algo, y otro responde." Un solo modelo cubre
 * BUSCAR_RIVAL, BUSCAR_JUGADOR, BUSCAR_REFUERZO y BUSCAR_EQUIPO. Nuevos tipos
 * se agregan como constante + forma de payload, sin migración.
 */
class Opportunity extends Model
{
    /** Tipos de oportunidad de Fase 1. */
    public const TYPE_BUSCAR_RIVAL    = 'BUSCAR_RIVAL';
    public const TYPE_BUSCAR_JUGADOR  = 'BUSCAR_JUGADOR';
    public const TYPE_BUSCAR_REFUERZO = 'BUSCAR_REFUERZO';
    public const TYPE_BUSCAR_EQUIPO   = 'BUSCAR_EQUIPO';

    public const TYPES = [
        self::TYPE_BUSCAR_RIVAL,
        self::TYPE_BUSCAR_JUGADOR,
        self::TYPE_BUSCAR_REFUERZO,
        self::TYPE_BUSCAR_EQUIPO,
    ];

    /** Estados del ciclo de vida. */
    public const STATUS_ABIERTA       = 'abierta';
    public const STATUS_EN_NEGOCIACION = 'en_negociacion';
    public const STATUS_CERRADA       = 'cerrada';
    public const STATUS_VENCIDA       = 'vencida';
    public const STATUS_CANCELADA     = 'cancelada';

    public const STATUSES = [
        self::STATUS_ABIERTA,
        self::STATUS_EN_NEGOCIACION,
        self::STATUS_CERRADA,
        self::STATUS_VENCIDA,
        self::STATUS_CANCELADA,
    ];

    /** Etiquetas legibles (voseo) para la UI. */
    public const TYPE_LABELS = [
        self::TYPE_BUSCAR_RIVAL    => 'Busca rival',
        self::TYPE_BUSCAR_JUGADOR  => 'Busca jugador',
        self::TYPE_BUSCAR_REFUERZO => 'Busca refuerzo',
        self::TYPE_BUSCAR_EQUIPO   => 'Jugador disponible',
    ];

    public const STATUS_LABELS = [
        self::STATUS_ABIERTA        => 'Abierta',
        self::STATUS_EN_NEGOCIACION => 'En negociación',
        self::STATUS_CERRADA        => 'Cerrada',
        self::STATUS_VENCIDA        => 'Vencida',
        self::STATUS_CANCELADA      => 'Cancelada',
    ];

    public function typeLabel(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Nombre del autor para mostrar (club publicante o jugador). */
    public function authorName(): string
    {
        return $this->club?->name ?? $this->user?->name ?? '—';
    }

    protected $fillable = [
        'type',
        'user_id',
        'club_id',
        'city',
        'zone',
        'venue_id',
        'required_level',
        'window_start',
        'window_end',
        'status',
        'is_hidden',
        'is_express',
        'payload',
        'expires_at',
    ];

    /** Default alineado con la migración. */
    protected $attributes = [
        'status' => self::STATUS_ABIERTA,
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'is_express'   => 'boolean',
            'window_start' => 'datetime',
            'window_end'   => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    // --- Relaciones ---

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(OpportunityResponse::class);
    }

    /** La (única) respuesta aceptada, si existe. */
    public function acceptedResponse(): HasOne
    {
        return $this->hasOne(OpportunityResponse::class)
            ->where('status', OpportunityResponse::STATUS_ACEPTADA);
    }

    /** Amistoso generado al aceptar (solo BUSCAR_RIVAL). */
    public function friendlyMatch(): HasOne
    {
        return $this->hasOne(FriendlyMatch::class);
    }

    public function venue(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    // --- Helpers semánticos de estado ---

    public function isAbierta(): bool
    {
        return $this->status === self::STATUS_ABIERTA;
    }

    public function isEnNegociacion(): bool
    {
        return $this->status === self::STATUS_EN_NEGOCIACION;
    }

    public function isCerrada(): bool
    {
        return $this->status === self::STATUS_CERRADA;
    }

    public function isCancelada(): bool
    {
        return $this->status === self::STATUS_CANCELADA;
    }

    /** Vencida por estado explícito o por fecha de vigencia pasada. */
    public function isVencida(): bool
    {
        if ($this->status === self::STATUS_VENCIDA) {
            return true;
        }

        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Acepta respuestas: abierta y no vencida. */
    public function canReceiveResponses(): bool
    {
        return $this->isAbierta() && ! $this->isVencida();
    }

    // --- Helpers de tipo ---

    public function isBuscarRival(): bool
    {
        return $this->type === self::TYPE_BUSCAR_RIVAL;
    }

    public function isBuscarJugador(): bool
    {
        return $this->type === self::TYPE_BUSCAR_JUGADOR;
    }

    public function isBuscarRefuerzo(): bool
    {
        return $this->type === self::TYPE_BUSCAR_REFUERZO;
    }

    public function isBuscarEquipo(): bool
    {
        return $this->type === self::TYPE_BUSCAR_EQUIPO;
    }

    // --- Scopes para el matching ---

    public function isHidden(): bool
    {
        return (bool) $this->is_hidden;
    }

    /** Modo rápido: vigencia corta para una necesidad de último momento. */
    public function isExpress(): bool
    {
        return (bool) $this->is_express;
    }

    public function scopeAbiertas($query)
    {
        return $query->where('status', self::STATUS_ABIERTA);
    }

    /** Excluye contenido oculto por moderación del listado público. */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Oportunidades vigentes para el listado de exploración: abiertas o en
     * negociación y cuya vigencia (expires_at → window_end → window_start) aún
     * no pasó. Portable a SQLite y MySQL vía COALESCE.
     */
    public function scopeActive($query)
    {
        return $query
            ->whereIn('status', [self::STATUS_ABIERTA, self::STATUS_EN_NEGOCIACION])
            ->where(function ($q) {
                $q->whereRaw('COALESCE(expires_at, window_end, window_start) IS NULL')
                  ->orWhereRaw('COALESCE(expires_at, window_end, window_start) >= ?', [now()]);
            });
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /** Solo oportunidades de modo rápido (express). */
    public function scopeExpress($query)
    {
        return $query->where('is_express', true);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeRequiringLevel($query, string $level)
    {
        return $query->where('required_level', $level);
    }
}
