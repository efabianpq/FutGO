<?php

namespace App\Models\Torneos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Control de idempotencia de recordatorios de partido (módulo Torneos, Sesión G). */
class TournamentMatchNotification extends Model
{
    public const TYPE_REMINDER = 'reminder';

    protected $fillable = ['user_id', 'match_id', 'type', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TournamentMatch::class, 'match_id');
    }
}
