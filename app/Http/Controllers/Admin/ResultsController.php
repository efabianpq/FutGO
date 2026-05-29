<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Services\PredictionsCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;
use RuntimeException;

class ResultsController extends Controller
{
    public function index(): View
    {
        $now = now();

        // Candidatos: upcoming/live cuya hora ya pasó + finalizados (para permitir recalcular)
        $pending = Game::whereIn('status', ['upcoming', 'live'])
            ->where('match_datetime', '<=', $now)
            ->orderBy('match_datetime')
            ->get();

        $finished = Game::where('status', 'finished')
            ->orderByDesc('match_datetime')
            ->limit(20)
            ->get();

        $mappedCount = Game::whereNotNull('api_match_id')->count();
        $lastSync = Game::where('status', 'finished')
            ->whereNotNull('api_match_id')
            ->max('updated_at');

        return view('admin.results.index', [
            'pending' => $pending,
            'finished' => $finished,
            'mappedCount' => $mappedCount,
            'lastSync' => $lastSync ? \Illuminate\Support\Carbon::parse($lastSync) : null,
        ]);
    }

    public function syncNow(): RedirectResponse
    {
        Artisan::call('results:sync');
        $output = trim(Artisan::output()) ?: 'Sin partidos activos.';

        return redirect()->route('admin.results.index')
            ->with('status', 'Sync ejecutado: ' . $output);
    }

    public function store(Request $request, Game $game, PredictionsCalculator $calculator): RedirectResponse
    {
        $data = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:20'],
            'away_score' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        $game->update([
            'home_score_official' => $data['home_score'],
            'away_score_official' => $data['away_score'],
            'status' => 'finished',
        ]);

        try {
            $r = $calculator->calculate($game->fresh());
        } catch (RuntimeException $e) {
            return back()->withErrors(['result' => $e->getMessage()]);
        }

        $d = $r['distribution'];
        $msg = sprintf(
            'Resultado guardado: %s %d-%d %s. Se calcularon %d pronósticos. Distribución: %d×5pts, %d×3pts, %d×2pts, %d×1pt, %d×0pts.',
            $game->home_team,
            $data['home_score'],
            $data['away_score'],
            $game->away_team,
            $r['predictions_count'],
            $d[5], $d[3], $d[2], $d[1], $d[0],
        );

        return back()->with('status', $msg);
    }
}
