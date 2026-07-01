<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\View\View;

class StatsController extends Controller
{
    public function index(Tournament $tournament): View
    {
        // Solo torneos públicos o si el usuario es admin/torneo_admin del torneo
        $this->authorizeView($tournament);

        $teams = $tournament->teams()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get();

        // Jugadores con al menos un partido jugado, ordenados por goles DESC, asistencias DESC
        $stats = PlayerStat::with(['teamPlayer.user', 'teamPlayer.team.club'])
            ->where('tournament_id', $tournament->id)
            ->where('matches_played', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderByDesc('matches_played')
            ->get();

        return view('torneos.estadisticas.index', compact('tournament', 'teams', 'stats'));
    }

    public function jugador(Tournament $tournament, TeamPlayer $teamPlayer): View
    {
        $this->authorizeView($tournament);

        // Verificar que el jugador pertenezca a un equipo del torneo
        abort_unless($teamPlayer->team->tournament_id === $tournament->id, 404);

        $teamPlayer->load(['user', 'team']);

        $stat = PlayerStat::where('tournament_id', $tournament->id)
            ->where('team_player_id', $teamPlayer->id)
            ->first();

        $phaseIds = $tournament->phases()->pluck('id');

        // Historial: partidos finished del torneo donde el jugador tuvo lineup
        $matchHistory = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->where('status', 'finished')
            ->whereHas('lineups', fn($q) => $q->where('team_player_id', $teamPlayer->id))
            ->with([
                'homeTeam',
                'awayTeam',
                'phase',
                'events'  => fn($q) => $q->where('team_player_id', $teamPlayer->id)->orderBy('minute'),
                'lineups' => fn($q) => $q->where('team_player_id', $teamPlayer->id),
            ])
            ->orderBy('match_number')
            ->get()
            ->map(function ($match) {
                return [
                    'match'  => $match,
                    'lineup' => $match->lineups->first(),
                    'events' => $match->events,
                ];
            });

        return view('torneos.estadisticas.jugador', compact(
            'tournament', 'teamPlayer', 'stat', 'matchHistory'
        ));
    }

    private function authorizeView(Tournament $tournament): void
    {
        // Accesible si el torneo es público o el usuario es admin/torneo_admin del torneo
        if ($tournament->isPublic()) {
            return;
        }

        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }

        $manages = $tournament->tournamentAdmins()
            ->where('user_id', $user->id)
            ->exists();

        $isPlayer = $tournament->teams()
            ->whereHas('players', fn($q) => $q->where('user_id', $user->id))
            ->exists();

        abort_unless($manages || $isPlayer, 403);
    }
}
