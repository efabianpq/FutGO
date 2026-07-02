<?php

namespace App\Models\Privacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centro de Privacidad · Prueba de consentimiento (append-only).
 *
 * document_type: privacy | terms | cookies | content | minors | marketing | parental
 * No usa updated_at (cada aceptación/revocación es una fila nueva e inmutable).
 */
class UserConsent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'document_type',
        'document_version',
        'accepted',
        'accepted_at',
        'ip',
        'user_agent',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'accepted'    => 'boolean',
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /** Último estado (aceptado/revocado) de un tipo para un usuario. */
    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('accepted_at')->orderByDesc('id');
    }
}
