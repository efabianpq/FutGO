<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\TournamentMatch;

/**
 * Cache de stats de torneo por club (deuda #3). Antes ClubController::show()
 * recalculaba esto en cada lectura con un ->get() sin límite de todos los
 * partidos finalizados históricos del club — crece con el historial. Ahora se
 * calcula una sola vez por evento de negocio (cierre de torneo / cron) y se
 * persiste en club_stats, igual que fair_play_scores/futgo_rankings.
 */
class ClubStatsService
{
    /** Recalcula y persiste las stats de un club. */
    public function refreshForClub(Club $club): ClubStat
    {
        $teamIds = $club->teams()->pluck('id');

        $matches = TournamentMatch::where('status', 'finished')
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->get(['home_team_id', 'away_team_id', 'home_score', 'away_score']);

        $agg = ['played' => 0, 'won' => 0, 'drawn' => 0, 'lost' => 0, 'goals_for' => 0, 'goals_against' => 0];
        foreach ($matches as $m) {
            $isHome    = $teamIds->contains($m->home_team_id);
            $forScore  = (int) ($isHome ? $m->home_score : $m->away_score);
            $againstSc = (int) ($isHome ? $m->away_score : $m->home_score);
            $agg['played']++;
            $agg['goals_for']     += $forScore;
            $agg['goals_against'] += $againstSc;
            if ($forScore > $againstSc)      $agg['won']++;
            elseif ($forScore < $againstSc)  $agg['lost']++;
            else                             $agg['drawn']++;
        }

        $tpIds = TeamPlayer::whereIn('team_id', $teamIds)->pluck('id');
        $topScorers = PlayerStat::whereIn('team_player_id', $tpIds)
            ->with('teamPlayer.user')
            ->get()
            ->groupBy(fn ($s) => $s->teamPlayer?->user_id ?? 'guest-' . $s->team_player_id)
            ->map(fn ($rows) => [
                'name'  => $rows->first()->teamPlayer?->displayName() ?? '—',
                'goals' => (int) $rows->sum('goals'),
            ])
            ->filter(fn ($r) => $r['goals'] > 0)
            ->sortByDesc('goals')
            ->take(10)
            ->values()
            ->all();

        return ClubStat::updateOrCreate(
            ['club_id' => $club->id],
            [...$agg, 'top_scorers' => $topScorers, 'calculated_at' => now()]
        );
    }

    /** Reconstrucción total (comando periódico). */
    public function rebuild(): void
    {
        Club::all()->each(fn (Club $club) => $this->refreshForClub($club));
    }
}
