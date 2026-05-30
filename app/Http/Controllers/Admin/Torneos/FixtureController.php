<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Http\RedirectResponse;
use Throwable;

class FixtureController extends Controller
{
    public function generate(Tournament $tournament, FixtureGeneratorService $generator): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        if (! $tournament->isOpen()) {
            return back()->with('error', 'El fixture solo se puede generar mientras el torneo está en inscripción (open).');
        }

        try {
            $summary = $generator->generate($tournament);
        } catch (Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('admin.torneos.show', $tournament)
            ->with('status', sprintf(
                'Fixture generado: %d fase(s), %d grupo(s), %d partido(s). El torneo está en juego.',
                $summary['phases'], $summary['groups'], $summary['matches'],
            ));
    }

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
}
