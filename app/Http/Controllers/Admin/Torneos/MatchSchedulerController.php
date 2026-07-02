<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * H6: gestión del fixture en formato "liga".
 *
 * El admin: activa la liga, agrega partidos a mano o auto-genera todos contra
 * todos, y genera la eliminatoria desde la tabla cuando los partidos terminaron.
 */
class MatchSchedulerController extends Controller
{
    public function __construct(private FixtureGeneratorService $fixture) {}

    /** Activa la liga: crea la fase y la tabla, pasa el torneo a in_progress. */
    public function activate(Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        try {
            $this->fixture->setupLeague($tournament);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Liga activada. Ya puedes cargar los partidos.');
    }

    /** Agrega un partido individual a la liga. */
    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        $data = $request->validate([
            'home_team_id' => ['required', 'integer', 'different:away_team_id'],
            'away_team_id' => ['required', 'integer'],
            'scheduled_at' => ['nullable', 'date'],
            'venue'        => ['nullable', 'string', 'max:100'],
        ], [
            'home_team_id.different' => 'El equipo local y el visitante deben ser distintos.',
        ]);

        try {
            $this->fixture->addLeagueMatch(
                $tournament,
                (int) $data['home_team_id'],
                (int) $data['away_team_id'],
                $data['scheduled_at'] ?? null,
                $data['venue'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Partido agregado a la liga.');
    }

    /** Auto-genera los partidos round-robin que falten. */
    public function autoRoundRobin(Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        try {
            $created = $this->fixture->generateLeagueRoundRobin($tournament);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Se generaron {$created} partidos (todos contra todos).");
    }

    /** Elimina un partido de la liga (solo si no tiene resultado). */
    public function destroy(Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        if ($match->status === 'finished') {
            return back()->with('error', 'No se puede eliminar un partido finalizado. Anula el resultado primero.');
        }

        $match->delete();

        return back()->with('status', 'Partido eliminado.');
    }

    /** Genera la eliminatoria con los mejores de la tabla. */
    public function generateKnockout(Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        try {
            $this->fixture->generateKnockoutFromStandings($tournament);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Eliminatoria generada a partir de la tabla de posiciones.');
    }

    private function authorizeAccess(Tournament $tournament): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) {
            return;
        }
        abort_unless($tournament->tournamentAdmins()->where('user_id', $user->id)->exists(), 403);
    }

    private function ensureBelongs(Tournament $tournament, TournamentMatch $match): void
    {
        $phaseIds = $tournament->phases()->pluck('id');
        abort_unless($phaseIds->contains($match->phase_id), 404);
    }
}
