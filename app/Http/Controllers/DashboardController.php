<?php

namespace App\Http\Controllers;

use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\View\View;

/**
 * Dashboard principal del usuario: muestra tarjetas según los módulos habilitados
 * (polla / torneos) y datos contextuales del usuario autenticado.
 */
class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $data = [
            'pollaAccess'     => $user->hasPollaAccess(),
            'torneosAccess'   => $user->hasTorneosAccess(),
            'myTournaments'   => collect(),
            'upcomingMatches' => collect(),
        ];

        if ($data['torneosAccess']) {
            $tournamentIds = $this->participatingTournamentIds($user->id);

            $data['myTournaments'] = Tournament::whereIn('id', $tournamentIds)
                ->withCount('teams')
                ->latest()
                ->get();

            if ($tournamentIds->isNotEmpty()) {
                $phaseIds = \App\Models\Torneos\TournamentPhase::whereIn('tournament_id', $tournamentIds)->pluck('id');

                $data['upcomingMatches'] = TournamentMatch::whereIn('phase_id', $phaseIds)
                    ->whereIn('status', ['scheduled', 'live'])
                    ->with(['homeTeam', 'awayTeam', 'phase', 'group'])
                    ->orderByRaw('scheduled_at IS NULL')
                    ->orderBy('scheduled_at')
                    ->orderBy('match_number')
                    ->limit(5)
                    ->get();
            }
        }

        return view('dashboard.index', $data);
    }

    /**
     * IDs de torneos donde el usuario participa: como admin del torneo,
     * capitán o jugador de un equipo.
     */
    private function participatingTournamentIds(int $userId): \Illuminate\Support\Collection
    {
        return Tournament::query()
            ->where(fn ($q) => $q
                ->whereHas('tournamentAdmins', fn ($a) => $a->where('user_id', $userId))
                ->orWhereHas('teams', fn ($t) => $t
                    ->where('captain_user_id', $userId)
                    ->orWhereHas('players', fn ($p) => $p->where('user_id', $userId))
                )
            )
            ->pluck('id');
    }
}
