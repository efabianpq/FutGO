<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Asignación de un logro a un jugador (Sesión F). */
class UserAchievement extends Model
{
    protected $fillable = ['user_id', 'achievement_id', 'awarded_at'];

    protected function casts(): array
    {
        return ['awarded_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function achievement(): BelongsTo
    {
        return $this->belongsTo(Achievement::class);
    }
}
