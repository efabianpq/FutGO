<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\FairPlayScore;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;

/**
 * Fair Play Score (Sesión F) — reputación de juego limpio.
 *
 * FÓRMULA (jugador), arranca en 100 y resta:
 *   score = max(0, 100 − 3·amarillas − 10·rojas − 5·inasistencias)
 *   inasistencias = convocatorias 'declinado' + 'convocado' a partido ya finalizado (no-show).
 *
 * FÓRMULA (equipo/club): promedio del fair play de sus jugadores registrados
 *   (o 100 si todavía no tiene jugadores con datos).
 *
 * Es un derivado cacheado en fair_play_scores; siempre reconstruible y alimenta el ranking.
 */
class FairPlayService
{
    public const YELLOW_PENALTY  = 3;
    public const RED_PENALTY     = 10;
    public const ABSENCE_PENALTY = 5;

    /** Recalcula y persiste el fair play de un jugador registrado. */
    public function refreshForUser(User $user): FairPlayScore
    {
        $teamPlayerIds = TeamPlayer::where('user_id', $user->id)->pluck('id');

        $sums = PlayerStat::whereIn('team_player_id', $teamPlayerIds)
            ->selectRaw('COALESCE(SUM(yellow_cards),0) y, COALESCE(SUM(red_cards),0) r, COALESCE(SUM(matches_played),0) m')
            ->first();

        $yellows  = (int) $sums->y;
        $reds     = (int) $sums->r;
        $matches  = (int) $sums->m;
        $absences = $this->countAbsences($teamPlayerIds);

        $score = $this->score($yellows, $reds, $absences);

        return FairPlayScore::updateOrCreate(
            ['subject_type' => 'player', 'subject_id' => $user->id],
            [
                'score' => $score, 'yellow_cards' => $yellows, 'red_cards' => $reds,
                'absences' => $absences, 'matches' => $matches, 'calculated_at' => now(),
            ]
        );
    }

    /** Recalcula el fair play de un club como promedio del de sus jugadores. */
    public function refreshForClub(Club $club): FairPlayScore
    {
        $userIds = ClubPlayer::where('club_id', $club->id)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        $scores = [];
        $teamPlayerIds = TeamPlayer::whereIn('team_id', $club->teams()->pluck('id'))->pluck('id');

        foreach ($userIds as $uid) {
            $scores[] = $this->refreshForUser(User::find($uid))->score;
        }

        $avg = empty($scores) ? 100 : (int) round(array_sum($scores) / count($scores));

        // Sumas del equipo (para mostrar), independientes del promedio.
        $sums = PlayerStat::whereIn('team_player_id', $teamPlayerIds)
            ->selectRaw('COALESCE(SUM(yellow_cards),0) y, COALESCE(SUM(red_cards),0) r, COALESCE(SUM(matches_played),0) m')
            ->first();

        return FairPlayScore::updateOrCreate(
            ['subject_type' => 'team', 'subject_id' => $club->id],
            [
                'score' => $avg, 'yellow_cards' => (int) $sums->y, 'red_cards' => (int) $sums->r,
                'absences' => $this->countAbsences($teamPlayerIds), 'matches' => (int) $sums->m,
                'calculated_at' => now(),
            ]
        );
    }

    /** Refresca jugadores y clubes que participaron en un torneo. */
    public function refreshForTournament(Tournament $tournament): void
    {
        $teamIds = $tournament->teams()->pluck('id');

        $userIds = TeamPlayer::whereIn('team_id', $teamIds)
            ->whereNotNull('user_id')->pluck('user_id')->unique();
        foreach (User::whereIn('id', $userIds)->get() as $u) {
            $this->refreshForUser($u);
        }

        $clubIds = $tournament->teams()->whereNotNull('club_id')->pluck('club_id')->unique();
        foreach (Club::whereIn('id', $clubIds)->get() as $club) {
            $this->refreshForClub($club);
        }
    }

    /** Reconstruye el fair play de todos (jugadores con inscripción + clubes). */
    public function rebuild(): void
    {
        $userIds = TeamPlayer::whereNotNull('user_id')->distinct()->pluck('user_id');
        foreach (User::whereIn('id', $userIds)->get() as $u) {
            $this->refreshForUser($u);
        }
        foreach (Club::all() as $club) {
            $this->refreshForClub($club);
        }
    }

    /** Fórmula de puntaje (piso 0, techo 100). */
    public function score(int $yellows, int $reds, int $absences): int
    {
        $value = 100
            - self::YELLOW_PENALTY * $yellows
            - self::RED_PENALTY * $reds
            - self::ABSENCE_PENALTY * $absences;

        return max(0, min(100, $value));
    }

    /** Inasistencias = convocatorias declinadas + convocado a partido ya finalizado. */
    private function countAbsences($teamPlayerIds): int
    {
        $declined = MatchCallUp::whereIn('team_player_id', $teamPlayerIds)
            ->where('status', 'declinado')->count();

        $noShow = MatchCallUp::whereIn('team_player_id', $teamPlayerIds)
            ->where('status', 'convocado')
            ->whereHas('match', fn ($q) => $q->where('status', 'finished'))
            ->count();

        return $declined + $noShow;
    }
}
