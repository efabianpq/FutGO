<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentMatch;
use Illuminate\View\View;

/**
 * Portal del Capitán (/capitan).
 *
 * Optimización N+1: phases, standings y matches se cargan en batch antes del map().
 */
class CaptainDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $teams = Team::where('captain_user_id', $user->id)
            ->with([
                'tournament.phases',   // fases en batch para todos los torneos
                'players.user',
            ])
            ->get();

        abort_if($teams->isEmpty(), 403, 'No sos capitán de ningún equipo.');

        // ── Batch: todos los phase IDs de los equipos del capitán ────────────
        $allPhaseIds = $teams
            ->map(fn ($team) => $team->tournament?->phases?->pluck('id') ?? collect())
            ->flatten()
            ->filter()
            ->unique()
            ->values();

        $allTeamIds = $teams->pluck('id');

        // ── Batch: standings de todos los equipos del capitán ────────────────
        $standingsByTeam = Standing::whereIn('team_id', $allTeamIds)
            ->whereIn('phase_id', $allPhaseIds)
            ->orderByDesc('last_calculated_at')
            ->get()
            ->groupBy('team_id')
            ->map(fn ($s) => $s->first());   // último standing calculado por equipo

        // ── Batch: partidos de todos los equipos del capitán ─────────────────
        $matchesByTeam = TournamentMatch::whereIn('phase_id', $allPhaseIds)
            ->where(fn ($q) => $q
                ->whereIn('home_team_id', $allTeamIds)
                ->orWhereIn('away_team_id', $allTeamIds))
            ->with(['homeTeam', 'awayTeam', 'phase', 'group'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get()
            ->groupBy(fn ($m) => $allTeamIds->contains($m->home_team_id)
                ? $m->home_team_id
                : $m->away_team_id);

        // ── Build cards en memoria ────────────────────────────────────────────
        $teamCards = $teams->map(function (Team $team) use ($standingsByTeam, $matchesByTeam) {
            $tournament = $team->tournament;

            $players  = $team->players->sortBy('jersey_number');
            $approved = $players->where('status', 'active')->values();
            $inactive = $players->where('status', 'inactive')->values();
            $pending  = $players->where('status', 'pending')->values();
            $rejected = $players->where('status', 'rejected')->values();

            $allMatches = $matchesByTeam->get($team->id, collect());
            $finished   = $allMatches->where('status', 'finished');
            $upcoming   = $allMatches->whereIn('status', ['scheduled', 'live'])->take(5)->values();
            $recent     = $finished->sortByDesc('match_number')->take(5)->values();

            $standing = $standingsByTeam->get($team->id);

            $gf = 0;
            $ga = 0;
            foreach ($finished as $m) {
                if ($m->home_team_id === $team->id) {
                    $gf += (int) $m->home_score;
                    $ga += (int) $m->away_score;
                } else {
                    $gf += (int) $m->away_score;
                    $ga += (int) $m->home_score;
                }
            }

            $stats = [
                'played'        => $standing?->played        ?? $finished->count(),
                'won'           => $standing?->won           ?? 0,
                'drawn'         => $standing?->drawn          ?? 0,
                'lost'          => $standing?->lost           ?? 0,
                'goals_for'     => $standing?->goals_for      ?? $gf,
                'goals_against' => $standing?->goals_against  ?? $ga,
            ];

            $alerts = [];
            if ($team->isPending()) {
                $alerts[] = 'El equipo está pendiente de aprobación del organizador.';
            }
            if ($team->isRejected()) {
                $alerts[] = 'El equipo fue rechazado por el organizador.';
            }
            if ($pending->isNotEmpty()) {
                $alerts[] = $pending->count() . ' solicitud(es) de jugadores esperan tu aprobación.';
            }
            $min = (int) ($tournament->min_players_per_team ?? 0);
            if ($min > 0 && $approved->count() < $min) {
                $alerts[] = 'Plantilla incompleta: ' . $approved->count() . ' de ' . $min . ' jugadores mínimos.';
            }
            if ($inactive->isNotEmpty()) {
                $alerts[] = $inactive->count() . ' jugador(es) suspendido(s) por tarjeta roja.';
            }

            return [
                'team'       => $team,
                'tournament' => $tournament,
                'approved'   => $approved,
                'inactive'   => $inactive,
                'pending'    => $pending,
                'rejected'   => $rejected,
                'upcoming'   => $upcoming,
                'recent'     => $recent,
                'stats'      => $stats,
                'alerts'     => $alerts,
            ];
        });

        return view('torneos.capitan', compact('teamCards'));
    }
}
