<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportConversation extends Model
{
    protected $fillable = [
        'ticket_id', 'user_id', 'messages', 'diagnostic_result', 'escalated', 'escalation_reason',
    ];

    protected $casts = [
        'messages'          => 'array',
        'diagnostic_result' => 'array',
        'escalated'         => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function addMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];
        $messages[] = ['role' => $role, 'content' => $content, 'created_at' => now()->toISOString()];
        $this->update(['messages' => $messages]);
    }
}
