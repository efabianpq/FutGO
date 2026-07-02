<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Torneos\ReputationService;
use Illuminate\Http\RedirectResponse;

/**
 * Recálculo manual del ranking/fair play FUTGO (deuda #10). Es de plataforma
 * (no por torneo puntual), por eso vive bajo el middleware 'admin' global.
 * Reusa la misma reconstrucción total del comando torneos:rebuild-reputation
 * (ReputationService::rebuildAll).
 */
class RankingController extends Controller
{
    public function recalculate(ReputationService $reputation): RedirectResponse
    {
        $reputation->rebuildAll();

        return back()->with('status', 'Ranking, fair play y logros recalculados correctamente.');
    }
}
