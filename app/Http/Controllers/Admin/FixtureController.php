<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FixtureController extends Controller
{
    private const PHASES = [
        'grupos' => 'Fase de Grupos',
        'dieciseisavos' => 'Dieciseisavos',
        'octavos' => 'Octavos',
        'cuartos' => 'Cuartos',
        'semifinal' => 'Semifinales',
        '3er_puesto' => 'Tercer Puesto',
        'final' => 'Final',
    ];

    public function index(): View
    {
        $games = Game::orderBy('match_datetime')->get();
        $phases = [];

        foreach (self::PHASES as $key => $label) {
            $phaseGames = $games->where('phase', $key)->values();
            if ($phaseGames->isEmpty()) continue;
            $phases[] = ['key' => $key, 'label' => $label, 'games' => $phaseGames];
        }

        return view('admin.fixture.index', compact('phases'));
    }

    public function edit(Game $game): View
    {
        return view('admin.fixture.edit', compact('game'));
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $data = $request->validate([
            'home_team' => ['required', 'string', 'max:60'],
            'away_team' => ['required', 'string', 'max:60'],
            'home_flag' => ['nullable', 'string', 'max:10'],
            'away_flag' => ['nullable', 'string', 'max:10'],
            'match_date' => ['required', 'date_format:Y-m-d'],
            'match_time' => ['required', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:100'],
        ]);

        $datetime = Carbon::createFromFormat(
            'Y-m-d H:i',
            $data['match_date'] . ' ' . $data['match_time'],
            'America/Bogota'
        );

        $game->update([
            'home_team' => $data['home_team'],
            'away_team' => $data['away_team'],
            'home_flag' => $data['home_flag'] ?? null,
            'away_flag' => $data['away_flag'] ?? null,
            'match_datetime' => $datetime,
            'lock_datetime' => $datetime->copy()->subMinutes(5),
            'venue' => $data['venue'] ?? 'Por definir',
        ]);

        return redirect()->route('admin.fixture.index')
            ->with('status', "Partido #{$game->match_number} actualizado.");
    }
}
