<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportFeatureRequest extends Model
{
    protected $fillable = ['ticket_id', 'title', 'description', 'status', 'votes_count'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'ticket_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(SupportFeatureVote::class, 'feature_request_id');
    }

    public function scopeVisible($q)
    {
        return $q->whereNotIn('status', ['descartado']);
    }

    public function scopeByVotes($q)
    {
        return $q->orderByDesc('votes_count');
    }

    public function hasVotedBy(User $user): bool
    {
        return $this->votes()->where('user_id', $user->id)->exists();
    }
}
