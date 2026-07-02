<?php

namespace App\Models\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFeatureVote extends Model
{
    protected $fillable = ['feature_request_id', 'user_id'];

    public function featureRequest(): BelongsTo
    {
        return $this->belongsTo(SupportFeatureRequest::class, 'feature_request_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
