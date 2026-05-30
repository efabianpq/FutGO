<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentPhase;
use App\Services\Torneos\PhaseClosureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

class PhaseController extends Controller
{
    public function __construct(
        private PhaseClosureService $closure,
    ) {}

    /** Pantalla de cierre de fase: resumen de partidos, clasificados y fase siguiente. */
    public function close(Tournament $tournament, TournamentPhase $phase): View
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $phase);

        $total     = $this->closure->totalMatches($phase);
        $finished  = $this->closure->finishedMatches($phase);
        $pending   = $total - $finished;
        $qualifiers = $this->closure->projectedQualifiers($phase);
        $nextPhase = $this->closure->nextKnockoutPhase($phase);
        $canClose  = $this->closure->canClose($phase);

        return view('torneos.phases.close', compact(
            'tournament', 'phase', 'total', 'finished', 'pending',
            'qualifiers', 'nextPhase', 'canClose'
        ));
    }

    /** Ejecuta el cierre de la fase y genera la eliminatoria. */
    public function doClose(Tournament $tournament, TournamentPhase $phase): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $phase);

        try {
            $this->closure->closeGroupPhase($phase);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.torneos.show', $tournament)
            ->with('status', "Fase \"{$phase->name}\" cerrada. Se generó la eliminatoria con los clasificados.");
    }

    // ───────────────────────── Helpers ─────────────────────────

    private function authorizeAccess(Tournament $tournament): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $manages = $tournament->tournamentAdmins()
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($manages, 403);
    }

    private function ensureBelongs(Tournament $tournament, TournamentPhase $phase): void
    {
        abort_unless($phase->tournament_id === $tournament->id, 404);
    }
}
