<?php

namespace App\Models\Torneos;

use Illuminate\Database\Eloquent\Model;

/**
 * Fair Play Score cacheado (Sesión F) de un jugador ('player') o equipo ('team').
 */
class FairPlayScore extends Model
{
    protected $fillable = [
        'subject_type', 'subject_id', 'score',
        'yellow_cards', 'red_cards', 'absences', 'matches', 'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'score'         => 'integer',
            'yellow_cards'  => 'integer',
            'red_cards'     => 'integer',
            'absences'      => 'integer',
            'matches'       => 'integer',
            'calculated_at' => 'datetime',
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
}
