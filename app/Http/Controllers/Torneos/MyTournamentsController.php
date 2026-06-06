<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * "Mis Torneos": entrada principal del módulo.
 *
 * Optimización N+1: todas las relaciones se cargan en batch antes del map().
 * El map() sólo opera sobre colecciones ya en memoria.
 */
class MyTournamentsController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // ── 1. Torneos visibles para el usuario (vista unificada H3) ──────────
        // - Admin de plataforma: ve TODOS los torneos (inventario global).
        // - Torneo_admin / capitán / jugador: ve los que administra o donde juega.
        $tournaments = Tournament::query()
            ->when(! $user->isAdmin(), fn ($query) => $query
                ->where(fn ($q) => $q
                    ->whereHas('tournamentAdmins', fn ($a) => $a->where('user_id', $user->id))
                    ->orWhereHas('teams', fn ($t) => $t
                        ->where('captain_user_id', $user->id)
                        ->orWhereHas('players', fn ($p) => $p->where('user_id', $user->id))
                    )
                )
            )
            ->withCount('teams')
            ->withCount(['teams as approved_teams_count' => fn ($q) => $q->where('status', 'approved')])
            ->with([
                // Fases cargadas en batch (evita N×2 queries en el map)
                'phases' => fn ($q) => $q->orderBy('order'),
                // Admin memberships del usuario para este conjunto de torneos
                'tournamentAdmins' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->latest()
            ->get();

        if ($tournaments->isEmpty()) {
            return view('torneos.index', ['cards' => collect(), 'isTorneoAdmin' => $user->isTorneoAdmin()]);
        }

        $tournamentIds = $tournaments->pluck('id');

        // ── 2. Equipos del usuario en estos torneos (batch) ───────────────────
        $myTeams = Team::whereIn('tournament_id', $tournamentIds)
            ->where(fn ($q) => $q
                ->where('captain_user_id', $user->id)
                ->orWhereHas('players', fn ($p) => $p->where('user_id', $user->id))
            )
            ->get()
            ->keyBy('tournament_id');   // indexed by tournament_id for O(1) lookup

        // ── 3. Phase IDs por torneo (ya en memoria) ───────────────────────────
        $phaseIdsByTournament = $tournaments->mapWithKeys(fn ($t) => [
            $t->id => $t->phases->pluck('id'),
        ]);

        $allPhaseIds = $phaseIdsByTournament->flatten()->filter()->unique()->values();

        // ── 4. Próximo partido por torneo/equipo (1 sola query) ───────────────
        // Traemos todos los partidos pending/live relevantes y los agrupamos en PHP
        $nextMatchesRaw = TournamentMatch::whereIn('phase_id', $allPhaseIds)
            ->whereIn('status', ['scheduled', 'live'])
            ->with(['homeTeam', 'awayTeam', 'phase', 'group'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get()
            ->groupBy('phase.tournament_id');   // agrupar por torneo

        // ── 5. Build cards en memoria ─────────────────────────────────────────
        $cards = $tournaments->map(function (Tournament $t) use ($user, $myTeams, $phaseIdsByTournament, $nextMatchesRaw) {
            $phaseIds  = $phaseIdsByTournament[$t->id] ?? collect();
            $myTeam    = $myTeams->get($t->id);

            $activePhase = $t->phases
                ->filter(fn ($p) => $p->is_active || $p->status === 'active')
                ->sortBy('order')
                ->first();

            // Partido siguiente: del equipo del usuario si tiene, si no del torneo.
            $tournamentMatches = $nextMatchesRaw->get($t->id, collect());
            if ($myTeam) {
                $nextMatch = $tournamentMatches
                    ->first(fn ($m) => $m->home_team_id === $myTeam->id || $m->away_team_id === $myTeam->id);
            } else {
                $nextMatch = $tournamentMatches->first();
            }

            // Administra: admins pre-loaded (filtrado por user_id en la query)
            $manages = $user->isAdmin() || $t->tournamentAdmins->isNotEmpty();

            return [
                'tournament'   => $t,
                'active_phase' => $activePhase,
                'next_match'   => $nextMatch,
                'my_team'      => $myTeam,
                'manages'      => $manages,
                'is_captain'   => $myTeam && $myTeam->captain_user_id === $user->id,
                'has_fixture'  => $phaseIds->isNotEmpty(),
                'has_groups'   => in_array($t->format, ['groups_and_knockout', 'round_robin'], true),
            ];
        });

        return view('torneos.index', [
            'cards'        => $cards,
            'isTorneoAdmin' => $user->isTorneoAdmin(),
        ]);
    }
}
