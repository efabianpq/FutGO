<?php

namespace App\Models\Privacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centro de Privacidad · Documento legal versionado.
 *
 * Tipos soportados (los 5 públicos obligatorios). `marketing`/`parental` no son
 * documentos publicables sino tipos de consentimiento (ver UserConsent).
 */
class LegalDocument extends Model
{
    public const TYPES = [
        'privacy'  => 'Política de privacidad',
        'terms'    => 'Términos y condiciones',
        'cookies'  => 'Política de cookies',
        'content'  => 'Política de contenido',
        'minors'   => 'Política para menores',
    ];

    /** Tipos cuya aceptación es obligatoria en el registro. */
    public const REQUIRED_AT_REGISTRATION = ['terms', 'privacy'];

    protected $fillable = [
        'type',
        'version',
        'title',
        'content',
        'summary_of_changes',
        'published_at',
        'is_current',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_current'   => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    // --- Scopes ---

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /** Versión vigente de un tipo (o null si no hay documento publicado). */
    public static function currentFor(string $type): ?self
    {
        return static::current()->ofType($type)->first();
    }
}
