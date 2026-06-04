<?php

namespace App\Services\Torneos;

use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\Tournament;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula las estadísticas individuales de los jugadores de un equipo.
 *
 * Fuente de verdad de participación: match_lineups.
 * - matches_played: partidos con fila en match_lineups.
 * - minutes_played: desde minute_in/minute_out del lineup; NULL → match_duration.
 * - clean_sheets: partidos con lineup donde el equipo no recibió goles.
 * - wins/draws/losses: resultado del equipo en partidos con lineup del jugador.
 * - goals/assists/cards: desde match_events (sin cambios).
 */
class PlayerStatsCalculatorService
{
    public function recalculate(Tournament $tournament, Team $team): void
    {
        $matchDuration = (int) ($tournament->match_duration ?? 90);
        $phaseIds      = $tournament->phases()->pluck('id');

        // Partidos finished del torneo donde el equipo participó
        $finishedMatches = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->where('status', 'finished')
            ->where(fn($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->get()
            ->keyBy('id');

        $matchIds = $finishedMatches->keys()->all();

        if (empty($matchIds)) {
            $this->resetTeamStats($tournament, $team);
            return;
        }

        // Lineups del equipo en esos partidos, agrupadas por team_player_id
        $allLineups = MatchLineup::whereIn('match_id', $matchIds)
            ->where('team_id', $team->id)
            ->get()
            ->groupBy('team_player_id');

        // Eventos de esos partidos para jugadores del equipo, agrupados por team_player_id
        $allEvents = MatchEvent::whereIn('match_id', $matchIds)
            ->whereHas('teamPlayer', fn($q) => $q->where('team_id', $team->id))
            ->get()
            ->groupBy('team_player_id');

        // Jugadores a calcular: todos los que tienen lineup O son activos actualmente
        $lineupPlayerIds  = $allLineups->keys();
        $activePlayerIds  = $team->players()->where('status', 'active')->pluck('id');
        $allPlayerIds     = $lineupPlayerIds->merge($activePlayerIds)->unique();
        $players          = TeamPlayer::whereIn('id', $allPlayerIds)->get()->keyBy('id');

        DB::transaction(function () use (
            $tournament, $team, $players, $allLineups, $allEvents,
            $finishedMatches, $matchDuration
        ) {
            foreach ($players as $player) {
                $lineups       = $allLineups->get($player->id, collect());
                $playerEvents  = $allEvents->get($player->id, collect());
                $eventsByMatch = $playerEvents->groupBy('match_id');

                $goals         = 0;
                $assists       = 0;
                $yellowCards   = 0;
                $redCards      = 0;
                $minutesPlayed = 0;
                $matchesPlayed = 0;
                $wins          = 0;
                $draws         = 0;
                $losses        = 0;
                $cleanSheets   = 0;
                $mvps          = 0;

                // Estadísticas de participación basadas en lineup
                foreach ($lineups as $lineup) {
                    $match = $finishedMatches->get($lineup->match_id);
                    if (! $match) {
                        continue;
                    }

                    $matchesPlayed++;
                    $minutesPlayed += $lineup->minutesPlayed($matchDuration);

                    // Figura del partido (MVP)
                    if ((int) $match->mvp_team_player_id === (int) $player->id) {
                        $mvps++;
                    }

                    // Resultado del equipo
                    $teamScore  = (int) ($match->home_team_id === $team->id ? $match->home_score : $match->away_score);
                    $rivalScore = (int) ($match->home_team_id === $team->id ? $match->away_score : $match->home_score);

                    if ($teamScore > $rivalScore) {
                        $wins++;
                    } elseif ($teamScore < $rivalScore) {
                        $losses++;
                    } else {
                        $draws++;
                    }

                    if ($rivalScore === 0) {
                        $cleanSheets++;
                    }
                }

                // Estadísticas de eventos (goles, tarjetas, etc.)
                foreach ($playerEvents as $event) {
                    match ($event->type) {
                        'goal'        => $goals++,
                        'assist'      => $assists++,
                        'yellow_card' => $yellowCards++,
                        'red_card'    => $redCards++,
                        default       => null,
                    };
                }

                PlayerStat::updateOrCreate(
                    [
                        'tournament_id'  => $tournament->id,
                        'team_player_id' => $player->id,
                    ],
                    [
                        'goals'              => $goals,
                        'assists'            => $assists,
                        'yellow_cards'       => $yellowCards,
                        'red_cards'          => $redCards,
                        'minutes_played'     => $minutesPlayed,
                        'matches_played'     => $matchesPlayed,
                        'wins'               => $wins,
                        'draws'              => $draws,
                        'losses'             => $losses,
                        'clean_sheets'       => $cleanSheets,
                        'mvps'               => $mvps,
                        'last_calculated_at' => now(),
                    ]
                );
            }
        });
    }

    private function resetTeamStats(Tournament $tournament, Team $team): void
    {
        $playerIds = $team->players()->pluck('id');
        PlayerStat::where('tournament_id', $tournament->id)
            ->whereIn('team_player_id', $playerIds)
            ->update([
                'goals'              => 0,
                'assists'            => 0,
                'yellow_cards'       => 0,
                'red_cards'          => 0,
                'minutes_played'     => 0,
                'matches_played'     => 0,
                'wins'               => 0,
                'draws'              => 0,
                'losses'             => 0,
                'clean_sheets'       => 0,
                'mvps'               => 0,
                'last_calculated_at' => now(),
            ]);
    }
}
