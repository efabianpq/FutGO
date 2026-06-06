<?php

namespace App\Services\Torneos;

use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;

/**
 * Consolida el acumulado histórico ("hoja de vida deportiva") del jugador.
 *
 * Es la ESCRITURA del derivado: suma los player_stats del usuario across TODOS
 * los torneos (vía team_players.user_id) y persiste el total en player_career_stats
 * (1 fila por usuario). La lectura del perfil es entonces O(1).
 *
 * Solo aplica a jugadores registrados (user_id no nulo); los jugadores
 * "por_verificar" no tienen acumulado hasta reclamar su perfil.
 */
class PlayerCareerStatsService
{
    /** Recalcula y persiste el acumulado de un usuario. */
    public function refreshForUser(User $user): PlayerCareerStat
    {
        $teamPlayerIds = TeamPlayer::where('user_id', $user->id)->pluck('id');
        $teamIds       = TeamPlayer::where('user_id', $user->id)->pluck('team_id')->unique();

        $sums = PlayerStat::whereIn('team_player_id', $teamPlayerIds)
            ->selectRaw('
                COALESCE(SUM(matches_played),0) AS matches_played,
                COALESCE(SUM(goals),0)          AS goals,
                COALESCE(SUM(assists),0)        AS assists,
                COALESCE(SUM(yellow_cards),0)   AS yellow_cards,
                COALESCE(SUM(red_cards),0)      AS red_cards,
                COALESCE(SUM(minutes_played),0) AS minutes_played,
                COALESCE(SUM(wins),0)           AS wins,
                COALESCE(SUM(draws),0)          AS draws,
                COALESCE(SUM(losses),0)         AS losses,
                COALESCE(SUM(clean_sheets),0)   AS clean_sheets,
                COALESCE(SUM(mvps),0)           AS mvps
            ')
            ->first();

        $tournamentsCount = $teamIds->isEmpty()
            ? 0
            : Team::whereIn('id', $teamIds)->distinct()->count('tournament_id');

        return PlayerCareerStat::updateOrCreate(
            ['user_id' => $user->id],
            [
                'matches_played'       => (int) $sums->matches_played,
                'goals'                => (int) $sums->goals,
                'assists'              => (int) $sums->assists,
                'yellow_cards'         => (int) $sums->yellow_cards,
                'red_cards'            => (int) $sums->red_cards,
                'minutes_played'       => (int) $sums->minutes_played,
                'wins'                 => (int) $sums->wins,
                'draws'                => (int) $sums->draws,
                'losses'               => (int) $sums->losses,
                'clean_sheets'         => (int) $sums->clean_sheets,
                'mvps'                 => (int) $sums->mvps,
                'tournaments_count'    => (int) $tournamentsCount,
                'teams_count'          => $teamIds->count(),
                'last_consolidated_at' => now(),
            ]
        );
    }

    /**
     * Refresca el acumulado de todos los usuarios registrados que participaron
     * en un torneo (jugadores con team_player vinculado a una cuenta).
     */
    public function refreshForTournament(Tournament $tournament): void
    {
        $teamIds = $tournament->teams()->pluck('id');

        $userIds = TeamPlayer::whereIn('team_id', $teamIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->refreshForUser($user);
        }
    }

    /** Refresca el acumulado de los usuarios registrados de un equipo concreto. */
    public function refreshForTeam(Team $team): void
    {
        $userIds = $team->players()
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->refreshForUser($user);
        }
    }

    /**
     * Elimina las contribuciones de un torneo del acumulado histórico de los
     * jugadores que participaron en él y recalcula sus totales.
     *
     * Se llama cuando un torneo se ELIMINA sin haber finalizado, de forma que
     * las stats ficticias de un torneo de prueba no contaminen el historial.
     * Los player_stats del torneo se borran en cascade junto al torneo.
     */
    public function removeForTournament(Tournament $tournament): void
    {
        // Identificar todos los usuarios registrados que participaron en el torneo.
        $teamIds = $tournament->teams()->pluck('id');

        $userIds = TeamPlayer::whereIn('team_id', $teamIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique();

        // Recalcular el acumulado de cada uno SIN las stats de este torneo.
        // Como los player_stats del torneo se borran en cascade al eliminar el
        // torneo (o justo antes de llamar este método), la suma ya no los incluirá.
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->refreshForUser($user);
        }
    }
}
