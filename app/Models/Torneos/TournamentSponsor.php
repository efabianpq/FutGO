<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Patrocinador de un torneo (Sesión G) — espacio de monetización, sin cobro. */
class TournamentSponsor extends Model
{
    protected $fillable = [
        'tournament_id', 'name', 'logo_url', 'link_url', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
