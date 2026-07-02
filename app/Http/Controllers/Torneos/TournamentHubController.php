<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\Tournament;
use Illuminate\View\View;

/**
 * Centro de Información del Torneo: punto único de consulta para participantes.
 * El control de acceso lo aplica el middleware ensure.tournament_participant.
 */
class TournamentHubController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $phaseIds = $tournament->phases()->pluck('id');

        // Fase activa (o la primera de grupos como fallback informativo).
        $activePhase = $tournament->phases()
            ->where('is_active', true)
            ->orderBy('order')
            ->first();

        // Próximos partidos (programados o en vivo), nulls de fecha al final.
        $upcomingMatches = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->whereIn('status', ['scheduled', 'live'])
            ->with(['homeTeam', 'awayTeam', 'phase', 'group'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->limit(5)
            ->get();

        // Últimos resultados (finalizados), más recientes primero.
        $latestResults = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->where('status', 'finished')
            ->with(['homeTeam', 'awayTeam', 'phase'])
            ->orderByDesc('match_number')
            ->limit(5)
            ->get();

        // Tabla de posiciones: primera fase de grupos con sus grupos y standings.
        $standingsPhase = $tournament->phases()
            ->where('type', 'groups')
            ->with([
                'groups' => fn ($q) => $q->orderBy('order')->orderBy('name'),
                'groups.standings' => fn ($q) => $q->with('team')->orderBy('position'),
            ])
            ->orderBy('order')
            ->first();

        // Top 10 goleadores con al menos un partido jugado.
        $topScorers = PlayerStat::with(['teamPlayer.user', 'teamPlayer.team'])
            ->where('tournament_id', $tournament->id)
            ->where('matches_played', '>', 0)
            ->orderByDesc('goals')
            ->orderByDesc('assists')
            ->orderByDesc('matches_played')
            ->limit(10)
            ->get();

        // Equipos participantes (aprobados) con capitán y conteo de jugadores.
        $teams = $tournament->teams()
            ->where('status', 'approved')
            ->with('captain')
            ->withCount('players')
            ->orderBy('name')
            ->get();

        // ¿El usuario puede ver la tabla completa (admin)? Define si se muestra el enlace.
        $canManage = $this->canManage($tournament);

        return view('torneos.hub.index', compact(
            'tournament',
            'activePhase',
            'upcomingMatches',
            'latestResults',
            'standingsPhase',
            'topScorers',
            'teams',
            'canManage'
        ));
    }

    /** ¿El usuario administra este torneo (admin global, creador o admin agregado del torneo)? */
    private function canManage(Tournament $tournament): bool
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return true;
        }

        return $tournament->tournamentAdmins()
            ->where('user_id', $user->id)
            ->exists();
    }
}
