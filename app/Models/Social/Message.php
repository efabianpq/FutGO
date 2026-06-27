<?php

namespace App\Models\Social;

use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FutGO Social — Message: mensaje de una conversación. En Fase 1 solo se usan
 * mensajes 'structured'; 'free' queda habilitado en el esquema para Fase 2.
 */
class Message extends Model
{
    /** El único habilitado en el MVP. */
    public const TYPE_STRUCTURED = 'structured';
    /** Mensajería libre — Fase 2. */
    public const TYPE_FREE = 'free';

    protected $fillable = [
        'conversation_id',
        'sender_user_id',
        'sender_club_id',
        'body',
        'type',
        'is_hidden',
    ];

    /** Default alineado con la migración (structured = MVP). */
    protected $attributes = [
        'type' => self::TYPE_STRUCTURED,
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function senderClub(): BelongsTo
    {
        return $this->belongsTo(Club::class, 'sender_club_id');
    }

    public function isStructured(): bool
    {
        return $this->type === self::TYPE_STRUCTURED;
    }

    public function isFree(): bool
    {
        return $this->type === self::TYPE_FREE;
    }

    /** ¿Lo escribió el sistema (mensaje estructurado sin emisor humano)? */
    public function isSystem(): bool
    {
        return $this->isStructured() && $this->sender_user_id === null;
    }

    public function isHidden(): bool
    {
        return (bool) $this->is_hidden;
    }

    // --- Scopes ---

    /** Excluye mensajes ocultados por moderación. */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }
}
