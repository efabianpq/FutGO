<?php

namespace App\Models;

use App\Models\Concerns\HasHashedDocument;
use App\Models\Concerns\HasPlayLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasHashedDocument, HasPlayLevel, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'futgo_id',
        'document',
        'document_hash',
        'birthdate',
        'phone_whatsapp',
        'avatar_url',
        'play_level',
        'city',
        'feed_last_read_at',
        'is_suspended',
        'suspended_until',
        'suspended_reason',
        'role',
        'notifications_enabled',
        'accepts_direct_messages',
        'email_verified_at',
        'delete_requested_at',
        'current_privacy_version',
        'current_terms_version',
        'guardian_email',
        'pending_guardian_consent',
    ];

    /**
     * Asigna automáticamente el identificador público FUTGO (FG-XXXXXX) al crear
     * un usuario, si no se le pasó uno. Cubre registro, seeders, tinker y tests.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->futgo_id)) {
                $user->futgo_id = \App\Services\Torneos\CredentialService::nextFutgoId();
            }
        });
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'is_suspended'       => 'boolean',
            'suspended_until'    => 'datetime',
            'notifications_enabled'      => 'boolean',
            'accepts_direct_messages'    => 'boolean',
            'feed_last_read_at'          => 'datetime',
            'delete_requested_at'        => 'datetime',
            'birthdate'                  => 'date',
            'pending_guardian_consent'   => 'boolean',
            'document'                   => 'encrypted',
            'phone_whatsapp'             => 'encrypted',
        ];
    }

    /** Edad en años cumplidos (o null si no cargó fecha de nacimiento). */
    public function age(): ?int
    {
        return $this->birthdate?->age;
    }

    /** ¿Es menor de edad (< 18)? Requiere birthdate cargada. */
    public function isMinor(): bool
    {
        $birthdate = $this->birthdate;

        return $birthdate !== null && $birthdate->age < 18;
    }

    /**
     * ¿El usuario está actualmente suspendido?
     * Considera tanto el flag como el vencimiento: si `suspended_until` ya pasó
     * la cuenta sigue activa (la pausa venció por tiempo).
     */
    public function isSuspended(): bool
    {
        if (! $this->is_suspended) {
            return false;
        }

        // Suspensión indefinida (sin fecha de vencimiento).
        if ($this->suspended_until === null) {
            return true;
        }

        return $this->suspended_until->isFuture();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /** Inscripciones por torneo (participaciones) que este usuario capitanea. */
    public function captainTeams(): HasMany
    {
        return $this->hasMany(\App\Models\Torneos\Team::class, 'captain_user_id');
    }

    /** Equipos PERMANENTES que este usuario capitanea. */
    public function captainClubs(): HasMany
    {
        return $this->hasMany(\App\Models\Torneos\Club::class, 'captain_user_id');
    }

    /** ¿Es capitán de al menos un equipo permanente? */
    public function isCaptainAnywhere(): bool
    {
        return $this->captainClubs()->exists();
    }

    /** Acumulado histórico del jugador (hoja de vida deportiva). */
    public function careerStat(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Torneos\PlayerCareerStat::class);
    }

    /** Reclamos de perfil iniciados por este usuario (vincular cuenta a 'por_verificar'). */
    public function profileClaims(): HasMany
    {
        return $this->hasMany(\App\Models\Torneos\ProfileClaim::class);
    }

    /** Logros (gamificación) obtenidos por el jugador. */
    public function achievements(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Torneos\Achievement::class, 'user_achievements')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    /** Fair Play Score cacheado del jugador (Sesión F). */
    public function fairPlayScore(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Torneos\FairPlayScore::class, 'subject_id')
            ->where('subject_type', 'player');
    }

    /** Iniciales para el avatar de respaldo cuando no hay foto. */
    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name ?? ''));
        $first = mb_substr($parts[0] ?? '', 0, 1);
        $last  = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
        return mb_strtoupper($first . $last) ?: '?';
    }

    /** ¿Está inscrito como jugador (titular/suplente) en algún equipo? */
    public function isTorneoPlayerAnywhere(): bool
    {
        return \App\Models\Torneos\TeamPlayer::where('user_id', $this->id)->exists();
    }

    /** Inscripciones por torneo (snapshot de plantilla) donde figura este usuario. */
    public function teamPlayers(): HasMany
    {
        return $this->hasMany(\App\Models\Torneos\TeamPlayer::class);
    }

    // --- FutGO Social (Fase 1) ---

    /** Oportunidades publicadas por este usuario (BUSCAR_EQUIPO, etc.). */
    public function opportunities(): HasMany
    {
        return $this->hasMany(\App\Models\Social\Opportunity::class);
    }

    /** Respuestas que este usuario dio a oportunidades de otros. */
    public function opportunityResponses(): HasMany
    {
        return $this->hasMany(\App\Models\Social\OpportunityResponse::class);
    }

    /** Entidades que sigue (clubs, jugadores, torneos). */
    public function follows(): HasMany
    {
        return $this->hasMany(\App\Models\Social\Follow::class);
    }

    /** Seguidores de este usuario (otros usuarios que lo siguen como jugador). */
    public function followers(): MorphMany
    {
        return $this->morphMany(\App\Models\Social\Follow::class, 'followable');
    }

    /** Eventos de confiabilidad de este usuario (no-shows, respuestas, etc.). */
    public function reliabilityEvents(): MorphMany
    {
        return $this->morphMany(\App\Models\Social\ReliabilityEvent::class, 'subject');
    }

    /** Score de confiabilidad cacheado del usuario. */
    public function reliabilityScore(): MorphOne
    {
        return $this->morphOne(\App\Models\Social\ReliabilityScore::class, 'subject');
    }

    /** Reportes de contenido emitidos por este usuario. */
    public function contentReports(): HasMany
    {
        return $this->hasMany(\App\Models\Social\ContentReport::class, 'reporter_user_id');
    }

    // --- H20: mensajería directa ---

    /** Usuarios que este usuario bloqueó. */
    public function blockedUsers(): HasMany
    {
        return $this->hasMany(\App\Models\UserBlock::class, 'user_id');
    }

    /** ¿Este usuario bloqueó a $target? */
    public function hasBlocked(User $target): bool
    {
        return $this->blockedUsers()->where('blocked_user_id', $target->id)->exists();
    }

    /** ¿Este usuario está bloqueado por $blocker? */
    public function isBlockedBy(User $blocker): bool
    {
        return $blocker->hasBlocked($this);
    }

    // --- Centro de Privacidad ---

    /** Configuración de privacidad del perfil (1:1). */
    public function privacySetting(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Models\Privacy\PrivacySetting::class);
    }

    /** Historial de consentimientos (aceptaciones/revocaciones). */
    public function consents(): HasMany
    {
        return $this->hasMany(\App\Models\Privacy\UserConsent::class);
    }

    /** Solicitudes de datos (export / delete). */
    public function dataRequests(): HasMany
    {
        return $this->hasMany(\App\Models\Privacy\DataRequest::class);
    }

    /** Registros de auditoría del usuario. */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Privacy\AuditLog::class);
    }

    /**
     * Config de privacidad garantizada: crea la fila con defaults si aún no existe
     * (usuarios previos al Centro de Privacidad o creados fuera del flujo de registro).
     */
    public function privacy(): \App\Models\Privacy\PrivacySetting
    {
        return $this->privacySetting()->firstOrCreate(
            ['user_id' => $this->id],
            \App\Models\Privacy\PrivacySetting::defaults()
        );
    }
}
