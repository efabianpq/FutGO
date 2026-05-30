<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Centro de Gestión de Equipos: dashboard del equipo del usuario dentro de un torneo.
 * El acceso a las mutaciones (aprobar/rechazar) lo refuerza ensure.team_member +
 * la verificación de capitán/torneo_admin en el propio controlador.
 */
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

    public function approvePlayer(Tournament $tournament, TeamPlayer $player): RedirectResponse
    {
        $this->authorizeManage($tournament, $player);

        if (! $player->isPending()) {
            return back()->with('error', 'Solo se pueden aprobar solicitudes pendientes.');
        }

        $player->update(['status' => 'active']);

        Log::info('torneos.team.player_approved', [
            'tournament_id'  => $tournament->id,
            'team_id'        => $player->team_id,
            'team_player_id' => $player->id,
            'player_user_id' => $player->user_id,
            'approved_by'    => auth()->id(),
        ]);

        return back()->with('status', 'Jugador aprobado e incorporado a la plantilla.');
    }

    public function rejectPlayer(Tournament $tournament, TeamPlayer $player): RedirectResponse
    {
        $this->authorizeManage($tournament, $player);

        if (! $player->isPending()) {
            return back()->with('error', 'Solo se pueden rechazar solicitudes pendientes.');
        }

        $player->update(['status' => 'rejected']);

        Log::info('torneos.team.player_rejected', [
            'tournament_id'  => $tournament->id,
            'team_id'        => $player->team_id,
            'team_player_id' => $player->id,
            'player_user_id' => $player->user_id,
            'rejected_by'    => auth()->id(),
        ]);

        return back()->with('status', 'Solicitud rechazada.');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Solo el capitán del equipo o un torneo_admin pueden gestionar la plantilla. */
    private function authorizeManage(Tournament $tournament, TeamPlayer $player): void
    {
        $user = auth()->user();
        $team = $player->team;

        // ensure.team_member ya garantizó pertenencia + que el equipo es del torneo.
        $isCaptain = $team->captain_user_id === $user->id;
        $isAdmin   = $user->isAdmin()
            || $tournament->tournamentAdmins()->where('user_id', $user->id)->exists();

        abort_unless($isCaptain || $isAdmin, 403, 'Solo el capitán puede gestionar la plantilla.');
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
