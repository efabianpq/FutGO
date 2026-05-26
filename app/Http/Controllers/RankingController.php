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
        $games = Game::orderBy('match_datetime')->get();
        $predictions = Prediction::where('user_id', $user->id)
            ->get()
            ->keyBy('match_id');

        $now = now();
        $phases = [];

        foreach (self::PHASES as $key => $label) {
            $phaseGames = $games->where('phase', $key)->values();
            if ($phaseGames->isEmpty()) {
                continue;
            }

            $rows = $phaseGames->map(function (Game $g) use ($predictions, $now) {
                $p = $predictions->get($g->id);
                $isLocked = $g->lock_datetime <= $now;
                $isFinished = $g->status === 'finished';

                return [
                    'match_number' => $g->match_number,
                    'home_team' => $g->home_team,
                    'away_team' => $g->away_team,
                    'home_flag' => $g->home_flag,
                    'away_flag' => $g->away_flag,
                    'date_label' => $g->match_datetime->locale('es')->isoFormat('ddd D MMM HH:mm'),
                    'group_name' => $g->group_name,
                    'is_finished' => $isFinished,
                    'is_locked' => $isLocked,
                    'official' => $isFinished
                        ? "{$g->home_score_official} - {$g->away_score_official}"
                        : null,
                    'prediction' => $p
                        ? "{$p->home_score} - {$p->away_score}"
                        : null,
                    'points_earned' => $p?->points_earned,
                ];
            })->all();

            $phases[] = ['key' => $key, 'label' => $label, 'rows' => $rows];
        }

        $ranking = DB::table('rankings')->where('user_id', $user->id)->first();

        return view('ranking.show', [
            'participant' => $user,
            'ranking' => $ranking,
            'phases' => $phases,
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
