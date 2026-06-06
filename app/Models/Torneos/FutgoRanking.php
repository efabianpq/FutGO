<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila cacheada del ranking FUTGO (Sesión F). Reconstruida por RankingService.
 */
class FutgoRanking extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'scope_type', 'scope_value', 'display_name',
        'score', 'matches_played', 'goals', 'assists', 'mvps', 'fair_play',
        'position', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score'          => 'integer',
            'matches_played' => 'integer',
            'goals'          => 'integer',
            'assists'        => 'integer',
            'mvps'           => 'integer',
            'fair_play'      => 'integer',
            'position'       => 'integer',
            'calculated_at'  => 'datetime',
        ];
    }

    public function scopePlayers($query)
    {
        return $query->where('subject_type', 'player');
    }

    public function scopeTeams($query)
    {
        return $query->where('subject_type', 'team');
    }

    /** Filtra por alcance: global, o ciudad/categoría concreta. */
    public function scopeForScope($query, string $type, ?string $value = null)
    {
        return $query->where('scope_type', $type)
            ->when($type === 'global', fn ($q) => $q->whereNull('scope_value'))
            ->when($type !== 'global', fn ($q) => $q->where('scope_value', $value));
    }
}
