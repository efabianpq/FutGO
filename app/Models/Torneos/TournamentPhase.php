<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TournamentPhase extends Model
{
    protected $fillable = [
        'tournament_id',
        'name',
        'type',
        'order',
        'is_active',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(TournamentGroup::class, 'phase_id');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(TournamentMatch::class, 'phase_id');
    }

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class, 'phase_id');
    }

    public function isGroups(): bool
    {
        return $this->type === 'groups';
    }

    public function isKnockout(): bool
    {
        return $this->type === 'knockout';
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isThirdPlace(): bool
    {
        return $this->type === 'third_place';
    }

    /** La fase fue cerrada: clasificados ya avanzados; no se editan sus resultados. */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
