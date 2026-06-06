<?php

namespace App\Services\Torneos;

use App\Models\Torneos\PlayerStat;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Historial de TEMPORADAS del jugador (Sesión F).
 *
 * Consolida (en lectura) la participación histórica agrupada por temporada (año
 * derivado de starts_at del torneo, o created_at si no tiene fecha de inicio).
 * Complementa el perfil permanente de la Sesión B sin nuevo esquema.
 */
class SeasonHistoryService
{
    /**
     * @return Collection<int,array{season:int,matches:int,goals:int,assists:int,mvps:int,tournaments:array}>
     */
    public function forUser(User $user): Collection
    {
        $stats = PlayerStat::whereHas('teamPlayer', fn ($q) => $q->where('user_id', $user->id))
            ->with(['tournament', 'teamPlayer.team'])
            ->get();

        return $stats
            ->groupBy(fn ($st) => $this->seasonOf($st))
            ->map(function (Collection $group, $season) {
                return [
                    'season'  => (int) $season,
                    'matches' => (int) $group->sum('matches_played'),
                    'goals'   => (int) $group->sum('goals'),
                    'assists' => (int) $group->sum('assists'),
                    'mvps'    => (int) $group->sum('mvps'),
                    'tournaments' => $group->map(fn ($st) => [
                        'tournament' => $st->tournament,
                        'team'       => $st->teamPlayer?->team,
                        'goals'      => (int) $st->goals,
                        'matches'    => (int) $st->matches_played,
                    ])->values()->all(),
                ];
            })
            ->sortKeysDesc()
            ->values();
    }

    private function seasonOf(PlayerStat $stat): int
    {
        $date = $stat->tournament?->starts_at ?? $stat->tournament?->created_at;

        return (int) ($date ? $date->format('Y') : now()->format('Y'));
    }
}
