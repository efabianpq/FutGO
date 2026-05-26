<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Game extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'phase',
        'group_name',
        'match_number',
        'home_team',
        'away_team',
        'home_flag',
        'away_flag',
        'match_datetime',
        'venue',
        'status',
        'home_score_official',
        'away_score_official',
        'lock_datetime',
        'api_match_id',
    ];

    protected function casts(): array
    {
        return [
            'match_datetime' => 'datetime',
            'lock_datetime' => 'datetime',
            'home_score_official' => 'integer',
            'away_score_official' => 'integer',
        ];
    }

    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }

    public function userPrediction(?int $userId = null): HasOne
    {
        return $this->hasOne(Prediction::class, 'match_id')
            ->where('user_id', $userId ?? auth()->id());
    }

    public function isLocked(): bool
    {
        return $this->lock_datetime <= now();
    }

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }
}
