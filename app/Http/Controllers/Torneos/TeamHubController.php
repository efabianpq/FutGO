<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeamHubController extends Controller
{
    public function index(Tournament $tournament): View|RedirectResponse
    {
        $team = $this->userTeamIn($tournament);

        // Quien no pertenece a un equipo del torneo no tiene equipo que consultar.
        if (! $team) {
            return redirect()
                ->route('torneos.equipo.inscribir', $tournament)
                ->with('error', 'Todavía no tenés un equipo en este torneo.');
        }

        $team->load([
            'captain',
            'players' => fn ($q) => $q->with('user')->orderBy('jersey_number'),
        ]);

        $isCaptain = $team->captain_user_id === auth()->id();

        // Partidos del equipo en el torneo (con eager loading).
        $phaseIds = $tournament->phases()->pluck('id');

        $teamMatches = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->where(fn ($q) => $q->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))
            ->with(['homeTeam', 'awayTeam', 'phase', 'group'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get();

        $upcomingMatches = $teamMatches->whereIn('status', ['scheduled', 'live'])->values();
        $recentResults   = $teamMatches->where('status', 'finished')->sortByDesc('match_number')->values();

        // Estadísticas: récord desde standings; goles desde partidos finished.
        $standing = Standing::where('team_id', $team->id)
            ->whereIn('phase_id', $phaseIds)
            ->orderByDesc('last_calculated_at')
            ->first();

        $goalsFor = 0;
        $goalsAgainst = 0;
        foreach ($recentResults as $m) {
            if ($m->home_team_id === $team->id) {
                $goalsFor     += (int) $m->home_score;
                $goalsAgainst += (int) $m->away_score;
            } else {
                $goalsFor     += (int) $m->away_score;
                $goalsAgainst += (int) $m->home_score;
            }
        }

        $stats = [
            'played' => $standing?->played ?? $recentResults->count(),
            'won'    => $standing?->won   ?? 0,
            'drawn'  => $standing?->drawn ?? 0,
            'lost'   => $standing?->lost  ?? 0,
            'goals_for'     => $goalsFor,
            'goals_against' => $goalsAgainst,
        ];

        // Plantilla segmentada.
        $activePlayers  = $team->players->whereIn('status', ['active', 'inactive']);
        $pendingPlayers = $team->players->where('status', 'pending');

        return view('torneos.equipos.hub', compact(
            'tournament', 'team', 'isCaptain',
            'activePlayers', 'pendingPlayers',
            'upcomingMatches', 'recentResults', 'stats'
        ));
    }

    /** Equipo del usuario autenticado en este torneo (capitán o jugador), o null. */
    private function userTeamIn(Tournament $tournament): ?Team
    {
        return Team::where('tournament_id', $tournament->id)
            ->where(fn ($q) => $q
                ->where('captain_user_id', auth()->id())
                ->orWhereHas('players', fn ($p) => $p->where('user_id', auth()->id()))
            )
            ->first();
    }
}
