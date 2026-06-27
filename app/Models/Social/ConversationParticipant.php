<?php

namespace App\Models\Social;

use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FutGO Social — ConversationParticipant: actor (usuario y/o club) dentro de un
 * hilo. `last_read_at` alimenta el contador de no leídos de la UI de Fase 2.
 */
class ConversationParticipant extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'club_id',
        'last_read_at',
    ];

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }
}
