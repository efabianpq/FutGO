<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Logro configurable del catálogo (Sesión F). Agregar logros nuevos = nuevas filas.
 */
class Achievement extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'icon',
        'metric', 'threshold', 'min_matches', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'threshold'   => 'integer',
            'min_matches' => 'integer',
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('awarded_at')
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
