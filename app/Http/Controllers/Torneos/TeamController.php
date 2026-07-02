<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Club;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\ClubMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inscripción a un torneo = ENROLAR un equipo permanente (club) existente.
 * La identidad y la plantilla del equipo se gestionan aparte (ClubController);
 * acá solo se elige cuál de los equipos del capitán participa en el torneo.
 */
class TeamController extends Controller
{
    public function __construct(private ClubMembershipService $membership) {}

    public function inscribir(Tournament $tournament): View|RedirectResponse
    {
        if (! $tournament->isOpen()) {
            return redirect()->route('torneos.index')
                ->with('error', 'Este torneo no está aceptando inscripciones.');
        }

        // Si el usuario ya participa en este torneo, ir a su panel.
        if ($this->userTeamIn($tournament)) {
            return redirect()->route('torneos.equipo.show', $tournament);
        }

        // Equipos permanentes que el usuario capitanea y que NO están aún enrolados.
        $enrolledClubIds = Team::where('tournament_id', $tournament->id)->pluck('club_id')->filter();
        $clubs = Club::where('captain_user_id', auth()->id())
            ->whereNotIn('id', $enrolledClubIds)
            ->withCount(['players as players_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return view('torneos.equipo.inscribir', compact('tournament', 'clubs'));
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        if (! $tournament->isOpen()) {
            return redirect()->route('torneos.index')
                ->with('error', 'Este torneo no está aceptando inscripciones.');
        }

        $data = $request->validate([
            'club_id' => ['required', 'integer', 'exists:clubs,id'],
        ], [
            'club_id.required' => 'Elige un equipo para inscribir.',
        ]);

        $club = Club::findOrFail($data['club_id']);

        // Solo el capitán del equipo permanente puede enrolarlo.
        if (! $club->isCaptainedBy($request->user())) {
            abort(403);
        }

        // El equipo no puede estar ya enrolado en este torneo.
        if (Team::where('tournament_id', $tournament->id)->where('club_id', $club->id)->exists()) {
            return back()->with('error', 'Ese equipo ya está inscrito en este torneo.');
        }

        // El capitán no puede tener dos equipos en el mismo torneo.
        if ($this->userTeamIn($tournament)) {
            return back()->with('error', 'Ya tienes un equipo inscrito en este torneo.');
        }

        $this->membership->enroll($club, $tournament);

        return redirect()->route('torneos.equipo.show', $tournament)
            ->with('status', 'Equipo inscripto. Espera la aprobación del organizador.');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Equipo (participación) del usuario autenticado en este torneo, o null. */
    private function userTeamIn(Tournament $tournament): ?Team
    {
        return Team::where('tournament_id', $tournament->id)
            ->where(fn ($q) => $q
                ->where('captain_user_id', auth()->id())
                ->orWhereHas('players', fn ($p) => $p->where('user_id', auth()->id())))
            ->first();
    }
}
