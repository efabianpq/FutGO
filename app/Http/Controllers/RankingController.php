<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RankingController extends Controller
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
        return view('ranking.index', [
            'rows' => $this->fetchRows(),
            'prizes' => Settings::prizeBreakdown(),
        ]);
    }

    public function data(): JsonResponse
    {
        return response()->json([
            'rows' => $this->fetchRows(),
            'prizes' => Settings::prizeBreakdown(),
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function show(User $user): View
    {
        // Solo partidos finalizados con puntos calculados — protege pronósticos
        // de partidos que aún no han iniciado.
        $games = Game::where('status', 'finished')
            ->orderBy('match_datetime')
            ->get();

        $predictionsRaw = Prediction::where('user_id', $user->id)
            ->whereNotNull('points_earned')
            ->whereIn('match_id', $games->pluck('id'))
            ->get()
            ->keyBy('match_id');

        $phases = [];
        $totalFinished = 0;

        foreach (self::PHASES as $key => $label) {
            $phaseGames = $games->where('phase', $key)->values();
            if ($phaseGames->isEmpty()) {
                continue;
            }

            // Filtrar a los que efectivamente tienen un pronóstico calculado
            // (si el user no jugó ese partido, mostramos 'Sin pronóstico' con 0 pts)
            $rows = $phaseGames->map(function (Game $g) use ($predictionsRaw) {
                $p = $predictionsRaw->get($g->id);
                return [
                    'match_number' => $g->match_number,
                    'home_team' => $g->home_team,
                    'away_team' => $g->away_team,
                    'home_flag' => $g->home_flag,
                    'away_flag' => $g->away_flag,
                    'date_label' => $g->match_datetime->locale('es')->isoFormat('ddd D MMM HH:mm'),
                    'group_name' => $g->group_name,
                    'official' => "{$g->home_score_official} - {$g->away_score_official}",
                    'prediction' => $p ? "{$p->home_score} - {$p->away_score}" : null,
                    'points_earned' => $p ? (int) $p->points_earned : 0,
                ];
            })->all();

            $totalFinished += count($rows);
            $phases[] = ['key' => $key, 'label' => $label, 'rows' => $rows];
        }

        $ranking = DB::table('rankings')->where('user_id', $user->id)->first();

        return view('ranking.show', [
            'participant' => $user,
            'ranking' => $ranking,
            'phases' => $phases,
            'totalFinished' => $totalFinished,
        ]);
    }

    private function fetchRows(): array
    {
        return DB::table('rankings')
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->where('users.is_active', true)
            ->orderBy('rankings.total_points', 'desc')
            ->orderBy('rankings.exact_predictions', 'desc')
            ->orderBy('users.id', 'asc')
            ->get([
                'users.id as user_id',
                'users.name',
                'rankings.total_points',
                'rankings.exact_predictions',
                'rankings.current_position',
                'rankings.previous_position',
                'rankings.last_calculated_at',
            ])
            ->map(fn ($r) => [
                'user_id' => (int) $r->user_id,
                'name' => $r->name,
                'total_points' => (int) $r->total_points,
                'exact_predictions' => (int) $r->exact_predictions,
                'current_position' => $r->current_position ? (int) $r->current_position : null,
                'previous_position' => $r->previous_position ? (int) $r->previous_position : null,
            ])
            ->all();
    }
}
