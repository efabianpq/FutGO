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

    /** ¿Es capitán de al menos un equipo en cualquier torneo? */
    public function isCaptainAnywhere(): bool
    {
        return \App\Models\Torneos\Team::where('captain_user_id', $this->id)->exists();
    }

    /** ¿Está inscrito como jugador (titular/suplente) en algún equipo? */
    public function isTorneoPlayerAnywhere(): bool
    {
        return \App\Models\Torneos\TeamPlayer::where('user_id', $this->id)->exists();
    }
}
