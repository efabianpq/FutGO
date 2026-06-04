<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'google_id',
        'phone_whatsapp',
        'avatar_url',
        'invitation_code',
        'is_active',
        'role',
        'modules',
        'notifications_enabled',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'notifications_enabled' => 'boolean',
        ];
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
}
