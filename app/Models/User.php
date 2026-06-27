<?php

namespace App\Models;

use App\Models\Concerns\HasPlayLevel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasPlayLevel, Notifiable;

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'futgo_id',
        'document',
        'phone_whatsapp',
        'avatar_url',
        'play_level',
        'city',
        'feed_last_read_at',
        'invitation_code',
        'is_active',
        'is_suspended',
        'suspended_until',
        'suspended_reason',
        'role',
        'modules',
        'notifications_enabled',
        'email_verified_at',
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
            'is_active'          => 'boolean',
            'is_suspended'       => 'boolean',
            'suspended_until'    => 'datetime',
            'notifications_enabled' => 'boolean',
            'feed_last_read_at'  => 'datetime',
        ];
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

    // --- Módulo Torneos ---

    public function hasModuleAccess(string $module): bool
    {
        if ($this->isAdmin()) return true;
        return $this->modules === 'full' || $this->modules === $module;
    }

    public function hasPollaAccess(): bool
    {
        return $this->hasModuleAccess('polla');
    }

    public function hasTorneosAccess(): bool
    {
        return $this->hasModuleAccess('torneos');
    }

    public function isTorneoAdmin(): bool
    {
        return $this->role === 'torneo_admin' || $this->isAdmin();
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
}
