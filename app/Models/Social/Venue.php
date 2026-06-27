<?php

namespace App\Models\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * FutGO Social — Venue: cancha del catálogo compartido.
 *
 * Cualquier usuario registrado puede registrar una cancha; no pertenece a ningún
 * club ni torneo. Se vincula opcionalmente a FriendlyMatch y Opportunity.
 */
class Venue extends Model
{
    public const SURFACES = [
        'cesped_natural'    => 'Césped natural',
        'cesped_sintetico'  => 'Césped sintético',
        'tierra'            => 'Tierra',
        'cemento'           => 'Cemento/asfalto',
        'parquet'           => 'Parquet/madera',
        'otro'              => 'Otro',
    ];

    protected $fillable = [
        'name',
        'slug',
        'city',
        'address',
        'surface_type',
        'approx_capacity',
        'maps_url',
        'photos',
        'registered_by_user_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'photos'           => 'array',
            'is_active'        => 'boolean',
            'approx_capacity'  => 'integer',
        ];
    }

    // --- Relaciones ---

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function friendlyMatches(): HasMany
    {
        return $this->hasMany(FriendlyMatch::class);
    }

    public function opportunities(): HasMany
    {
        return $this->hasMany(Opportunity::class);
    }

    // --- Helpers ---

    public function surfaceLabel(): string
    {
        return self::SURFACES[$this->surface_type] ?? ($this->surface_type ?? '—');
    }

    /** ¿El usuario dado puede editar esta cancha? */
    public function canBeEditedBy(User $user): bool
    {
        return $user->id === $this->registered_by_user_id
            || in_array($user->role, ['admin', 'super_admin'], true);
    }

    /**
     * Partidos (amistosos) jugados en esta cancha.
     * Solo los `jugado` (resultado confirmado).
     */
    public function playedFriendlyMatches()
    {
        return $this->friendlyMatches()->jugados()->with(['homeClub', 'awayClub'])->latest('scheduled_at');
    }

    /**
     * ¿Hay amistosos confirmados programados en el futuro en esta cancha?
     * Se usa para mostrar "disponibilidad" en el perfil público.
     */
    public function upcomingMatches()
    {
        return $this->friendlyMatches()
            ->confirmados()
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at')
            ->with(['homeClub', 'awayClub']);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeInCity($query, string $city)
    {
        return $query->where('city', $city);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('address', 'like', "%{$term}%");
        });
    }

    // --- Slug automático ---

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $venue) {
            if (empty($venue->slug)) {
                $venue->slug = static::generateUniqueSlug($venue->name);
            }
        });
    }

    public static function generateUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
