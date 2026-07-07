<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportArticle extends Model
{
    protected $fillable = [
        'title', 'slug', 'content', 'excerpt', 'category', 'source',
        'source_ticket_id', 'helpful_count', 'not_helpful_count', 'is_published',
    ];

    protected $casts = ['is_published' => 'boolean'];

    public function scopePublished($q)
    {
        return $q->where('is_published', true);
    }

    public function scopeForFeatureKey($q, string $key)
    {
        return $q->whereHas('topics', fn ($t) => $t->where('feature_key', $key));
    }

    public function sourceTicket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'source_ticket_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(SupportArticleTopic::class);
    }
}
