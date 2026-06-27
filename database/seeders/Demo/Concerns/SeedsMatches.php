<?php

namespace Database\Seeders\Demo\Concerns;

use App\Models\Torneos\Club;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\ClubMembershipService;
use Carbon\Carbon;
use Database\Seeders\Demo\DemoData;

/**
 * Helpers compartidos por los seeders de torneos: enrolar clubs, armar
 * alineaciones, cargar resultados creíbles (goles/asistencias/tarjetas/MVP) y
 * generar convocatorias. Mantiene la lógica de "jugar un partido" en un solo
 * lugar para que los 5 torneos sean coherentes.
 */
trait SeedsMatches
{
    /**
     * Enrola los clubs (por slug) en el torneo y los deja APROBADOS.
     * Usa el servicio real (copia la plantilla permanente como snapshot).
     *
     * @param  array<int,string> $slugs
     * @return array<string,Team> mapa slug => Team
     */
    protected function enrollApproved(Tournament $tournament, array $slugs): array
    {
        $teams = [];
        $membership = app(ClubMembershipService::class);

        foreach ($slugs as $slug) {
            $club = DemoData::club($slug);
            $team = $membership->enroll($club, $tournament);
            $team->update(['status' => 'approved']);
            $teams[$slug] = $team->fresh();
        }

        return $teams;
    }

    /**
     * 11 titulares de un equipo, priorizando jugadores REGISTRADOS (con cuenta)
     * para que las estadísticas/carrera/ranking se acumulen sobre usuarios reales.
     *
     * @return \Illuminate\Support\Collection<int,TeamPlayer>
     */
    protected function pickStarters(Team $team, int $count = 11): \Illuminate\Support\Collection
    {
        return $team->players()
            ->where('status', 'active')
            ->orderByRaw('user_id IS NULL')   // registrados primero
            ->orderBy('id')
            ->limit($count)
            ->get();
    }

    /** Suplentes (los siguientes activos tras los titulares). */
    protected function pickSubs(Team $team, int $skip, int $count): \Illuminate\Support\Collection
    {
        return $team->players()
            ->where('status', 'active')
            ->orderByRaw('user_id IS NULL')
            ->orderBy('id')
            ->skip($skip)
            ->take($count)
            ->get();
    }

    /**
     * Marca un partido como jugado con un resultado creíble: fija marcador/ganador,
     * crea alineaciones (con algún cambio), goles, asistencias, tarjetas y MVP.
     *
     * @param array{when?:Carbon, subs?:int, mvp?:bool, yellows?:int, reds?:int} $opts
     */
    protected function playMatch($match, Team $home, Team $away, int $homeScore, int $awayScore, array $opts = []): void
    {
        $when = $opts['when'] ?? Carbon::now()->subDays(7);

        $match->home_score     = $homeScore;
        $match->away_score     = $awayScore;
        $match->winner_team_id = match (true) {
            $homeScore > $awayScore => $home->id,
            $awayScore > $homeScore => $away->id,
            default                 => null,
        };
        $match->status       = 'finished';
        $match->scheduled_at = $when;

        // Alineaciones (11 titulares + algunos suplentes).
        $homeStarters = $this->pickStarters($home);
        $awayStarters = $this->pickStarters($away);
        $this->createLineups($match, $home, $homeStarters, true);
        $this->createLineups($match, $away, $awayStarters, true);

        $subs = $opts['subs'] ?? 0;
        if ($subs > 0) {
            $this->createLineups($match, $home, $this->pickSubs($home, 11, $subs), false);
            $this->createLineups($match, $away, $this->pickSubs($away, 11, $subs), false);
        }

        // MVP (figura): id explícito si se indicó; si no, un titular registrado
        // del ganador (o del local si empate).
        if (! empty($opts['mvpTeamPlayerId'])) {
            $match->mvp_team_player_id = $opts['mvpTeamPlayerId'];
        } elseif (($opts['mvp'] ?? false)) {
            $mvpTeam     = $match->winner_team_id === $away->id ? $awayStarters : $homeStarters;
            $mvp         = $mvpTeam->firstWhere('user_id', '!=', null) ?? $mvpTeam->first();
            $match->mvp_team_player_id = $mvp?->id;
        }

        $match->save();

        // Goles + asistencias coherentes con el marcador.
        if ($homeScore > 0) {
            $this->createGoals($match, $homeStarters, $homeScore);
        }
        if ($awayScore > 0) {
            $this->createGoals($match, $awayStarters, $awayScore);
        }

        // Disciplina: tarjetas amarillas / rojas.
        foreach (['yellow_card' => $opts['yellows'] ?? 1, 'red_card' => $opts['reds'] ?? 0] as $type => $n) {
            for ($i = 0; $i < $n; $i++) {
                $pool = $i % 2 === 0 ? $homeStarters : $awayStarters;
                $this->createCard($match, $pool, $type, 30 + $i * 15);
            }
        }
    }

    /** Programa un partido a futuro (sin resultado). */
    protected function scheduleMatch($match, Carbon $when, ?string $venue = null): void
    {
        $match->update([
            'status'       => 'scheduled',
            'scheduled_at' => $when,
            'venue'        => $venue,
        ]);
    }

    /** Crea alineaciones para un conjunto de jugadores. */
    protected function createLineups($match, Team $team, \Illuminate\Support\Collection $players, bool $started): void
    {
        foreach ($players as $tp) {
            MatchLineup::create([
                'match_id'       => $match->id,
                'team_player_id' => $tp->id,
                'team_id'        => $team->id,
                'started'        => $started,
                'minute_in'      => $started ? 0 : 60,
                'minute_out'     => null,
            ]);
        }
    }

    /** Reparte goles (con asistencias) entre los titulares, priorizando ofensivos. */
    protected function createGoals($match, \Illuminate\Support\Collection $starters, int $goals): void
    {
        $scorers = $starters->filter(
            fn ($p) => str_contains((string) $p->position, 'Delantero') || str_contains((string) $p->position, 'Extremo')
        )->values();

        if ($scorers->isEmpty()) {
            $scorers = $starters->values();
        }

        $minutes = [8, 19, 27, 38, 52, 63, 71, 84, 90];

        for ($g = 0; $g < $goals; $g++) {
            $scorer = $scorers[$g % $scorers->count()];
            $minute = $minutes[$g % count($minutes)];

            MatchEvent::create([
                'match_id'       => $match->id,
                'team_player_id' => $scorer->id,
                'type'           => 'goal',
                'minute'         => $minute,
            ]);

            // Asistencia desde otro titular (no el goleador).
            $assister = $starters->firstWhere('id', '!=', $scorer->id);
            if ($assister) {
                MatchEvent::create([
                    'match_id'       => $match->id,
                    'team_player_id' => $assister->id,
                    'type'           => 'assist',
                    'minute'         => $minute,
                ]);
            }
        }
    }

    /** Crea una tarjeta sobre un jugador defensivo del conjunto dado. */
    protected function createCard($match, \Illuminate\Support\Collection $starters, string $type, int $minute): void
    {
        $player = $starters->first(fn ($p) => str_contains((string) $p->position, 'Defensa'))
            ?? $starters->get(2)
            ?? $starters->first();

        if ($player) {
            MatchEvent::create([
                'match_id'       => $match->id,
                'team_player_id' => $player->id,
                'type'           => $type,
                'minute'         => $minute,
            ]);
        }
    }

    /**
     * Genera convocatorias para un partido con una mezcla de estados.
     *
     * @param array{confirmado?:int, convocado?:int, declinado?:int} $mix
     */
    protected function createCallUps($match, Team $team, array $mix): void
    {
        $players = $this->pickStarters($team, 16);
        $i = 0;

        foreach (['confirmado', 'convocado', 'declinado'] as $status) {
            $n = $mix[$status] ?? 0;
            for ($k = 0; $k < $n && $i < $players->count(); $k++, $i++) {
                $tp = $players[$i];
                MatchCallUp::create([
                    'match_id'       => $match->id,
                    'team_player_id' => $tp->id,
                    'team_id'        => $team->id,
                    'status'         => $status,
                    'responded_at'   => $status === 'convocado' ? null : Carbon::now()->subDays(1),
                ]);
            }
        }
    }
}
