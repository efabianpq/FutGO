<?php

namespace App\Models\Social;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * FutGO Social — Conversation: hilo de mensajes, opcionalmente vinculado a una
 * Opportunity o FriendlyMatch. Esquema listo para mensajería libre (Fase 2).
 */
class Conversation extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    // --- Relaciones ---

    /** Entidad vinculada (Opportunity o FriendlyMatch), si la hay. */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Último mensaje del hilo (para la lista de conversaciones). */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    // --- Helpers ---

    /** ¿Está vinculada a alguna entidad (oportunidad/partido)? */
    public function isLinked(): bool
    {
        return $this->subject_type !== null;
    }

    /** ¿El usuario es participante de esta conversación? */
    public function hasParticipant(User $user): bool
    {
        return $this->participants->contains(fn ($p) => $p->user_id === $user->id);
    }

    /** El participante que representa a este usuario (o null). */
    public function participantFor(User $user): ?ConversationParticipant
    {
        return $this->participants->firstWhere('user_id', $user->id);
    }

    // --- Scopes ---

    /** Conversaciones en las que el usuario participa, recientes primero. */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query
            ->whereHas('participants', fn (Builder $q) => $q->where('user_id', $user->id))
            ->orderByRaw('last_message_at IS NULL')
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');
    }
}
