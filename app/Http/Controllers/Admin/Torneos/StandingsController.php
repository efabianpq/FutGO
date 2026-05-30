<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Tournament;
use App\Services\Torneos\StandingsCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class StandingsController extends Controller
{
    public function __construct(
        private StandingsCalculatorService $calculator,
    ) {}

    public function index(Tournament $tournament): View
    {
        $this->authorizeAccess($tournament);

        // Fases de grupos con sus grupos, equipos y standings
        $phases = $tournament->phases()
            ->where('type', 'groups')
            ->with([
                'groups.teams',
                'groups.standings' => fn($q) => $q->with('team')->orderBy('position'),
            ])
            ->orderBy('order')
            ->get();

        $classifiesPerGroup = (int) ($tournament->classifies_per_group ?? 1);

        return view('admin.torneos.standings.index', compact(
            'tournament', 'phases', 'classifiesPerGroup'
        ));
    }

    public function recalculate(Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        $phases = $tournament->phases()
            ->where('type', 'groups')
            ->where('status', '!=', 'completed')
            ->get();

        if ($phases->isEmpty()) {
            return back()->with('error', 'No hay fases de grupos abiertas para recalcular (las fases cerradas quedan congeladas).');
        }

        foreach ($phases as $phase) {
            $this->calculator->recalculate($phase);
        }

        return back()->with('status', 'Tabla de posiciones recalculada correctamente.');
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
