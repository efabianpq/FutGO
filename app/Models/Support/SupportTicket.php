<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicket extends Model
{
    protected $fillable = [
        'user_id', 'category', 'status', 'priority', 'classifier_confidence',
        'subject', 'context_snapshot', 'error_trace', 'audit_timeline',
        'assigned_to', 'resolution_notes', 'satisfaction_response',
        'resolved_at', 'satisfaction_sent_at',
    ];

    protected $casts = [
        'context_snapshot'      => 'array',
        'error_trace'           => 'array',
        'audit_timeline'        => 'array',
        'resolved_at'           => 'datetime',
        'satisfaction_sent_at'  => 'datetime',
        'classifier_confidence' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(SupportConversation::class, 'ticket_id');
    }

    public function featureRequest(): HasOne
    {
        return $this->hasOne(SupportFeatureRequest::class, 'ticket_id');
    }

    public function scopeOpen($q)
    {
        return $q->whereNotIn('status', ['resuelto', 'cerrado']);
    }

    public function scopePending($q)
    {
        return $q->where('status', 'abierto');
    }

    public function scopeUrgent($q)
    {
        return $q->whereIn('priority', ['critica', 'alta']);
    }

    public function isResolved(): bool
    {
        return in_array($this->status, ['resuelto', 'cerrado']);
    }

    public function needsSatisfaction(): bool
    {
        return $this->status === 'resuelto'
            && is_null($this->satisfaction_sent_at)
            && is_null($this->satisfaction_response);
    }
}
