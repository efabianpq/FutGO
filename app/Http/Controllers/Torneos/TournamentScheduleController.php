<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\View\View;

class TournamentScheduleController extends Controller
{
    /**
     * Cronograma general del torneo: todos los partidos agrupados por fase,
     * con datos para los filtros Alpine (fase, grupo, equipo, estado).
     */
    public function index(Tournament $tournament): View
    {
        // Carga todas las fases con sus grupos y partidos en 1 consulta por nivel.
        $phases = $tournament->phases()
            ->with([
                'groups',
                'matches' => fn ($q) => $q
                    ->with(['homeTeam', 'awayTeam', 'group'])
                    ->orderBy('scheduled_at')
                    ->orderBy('match_number'),
            ])
            ->orderBy('order')
            ->get();

        // Equipos aprobados para el filtro Alpine.
        $teams = $tournament->teams()
            ->where('status', 'approved')
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return view('torneos.schedule.index', compact('tournament', 'phases', 'teams'));
    }

    /**
     * Cronograma de un equipo específico: próximos partidos, historial y récord.
     */
    public function team(Tournament $tournament, Team $team): View
    {
        abort_unless($team->tournament_id === $tournament->id, 404);

        // Todos los partidos del equipo en el torneo.
        $matches = TournamentMatch::whereHas(
                'phase',
                fn ($q) => $q->where('tournament_id', $tournament->id)
            )
            ->where(fn ($q) => $q
                ->where('home_team_id', $team->id)
                ->orWhere('away_team_id', $team->id)
            )
            ->with(['homeTeam', 'awayTeam', 'phase', 'group'])
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get();

        $upcoming = $matches->where('status', 'scheduled')->values();
        $finished = $matches->where('status', 'finished')->values();

        // Récord del equipo calculado desde standings (ya calculados por el servicio).
        $standing = Standing::where('team_id', $team->id)
            ->whereIn('phase_id', $tournament->phases()->pluck('id'))
            ->orderByDesc('last_calculated_at')
            ->first();

        // Resumen de goles a favor/en contra desde los partidos finished.
        $goalsFor     = 0;
        $goalsAgainst = 0;
        foreach ($finished as $m) {
            if ($m->home_team_id === $team->id) {
                $goalsFor     += (int) $m->home_score;
                $goalsAgainst += (int) $m->away_score;
            } else {
                $goalsFor     += (int) $m->away_score;
                $goalsAgainst += (int) $m->home_score;
            }
        }

        return view('torneos.schedule.team', compact(
            'tournament', 'team', 'matches', 'upcoming', 'finished',
            'standing', 'goalsFor', 'goalsAgainst'
        ));
    }
}
