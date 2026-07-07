<?php

namespace App\Models\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportArticleTopic extends Model
{
    protected $fillable = ['support_article_id', 'feature_key'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(SupportArticle::class, 'support_article_id');
    }
}
