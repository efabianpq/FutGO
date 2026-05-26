<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Prediction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PredictionsController extends Controller
{
    // Orden de fases para la vista
    private const PHASES = [
        'grupos' => 'Fase de Grupos',
        'dieciseisavos' => 'Dieciseisavos de Final',
        'octavos' => 'Octavos de Final',
        'cuartos' => 'Cuartos de Final',
        'semifinal' => 'Semifinales',
        '3er_puesto' => 'Tercer y Cuarto Puesto',
        'final' => 'Final',
    ];

    public function index(Request $request): View
    {
        $userId = $request->user()->id;

        $games = Game::with(['userPrediction' => fn ($q) => $q->where('user_id', $userId)])
            ->orderBy('match_datetime')
            ->get();

        $now = now();

        $phases = [];
        foreach (self::PHASES as $key => $label) {
            $phaseGames = $games->where('phase', $key)->values();
            if ($phaseGames->isEmpty()) {
                continue;
            }

            $phases[] = [
                'key' => $key,
                'label' => $label,
                'matches' => $phaseGames->map(fn ($g) => $this->serializeGame($g, $now))->all(),
            ];
        }

        $groups = $games->whereNotNull('group_name')
            ->pluck('group_name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return view('predictions.index', [
            'phases' => $phases,
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:20'],
            'away_score' => ['required', 'integer', 'min:0', 'max:20'],
        ]);

        if ($game->isLocked()) {
            return response()->json([
                'ok' => false,
                'error' => 'El pronóstico de este partido ya está bloqueado.',
            ], 422);
        }

        $prediction = Prediction::updateOrCreate(
            ['user_id' => $request->user()->id, 'match_id' => $game->id],
            [
                'home_score' => $data['home_score'],
                'away_score' => $data['away_score'],
                'is_locked' => false,
            ]
        );

        return response()->json([
            'ok' => true,
            'prediction' => [
                'home_score' => $prediction->home_score,
                'away_score' => $prediction->away_score,
                'updated_at' => $prediction->updated_at->toIso8601String(),
            ],
        ]);
    }

    public function states(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $now = now();

        $games = Game::with(['userPrediction' => fn ($q) => $q->where('user_id', $userId)])
            ->get(['id', 'lock_datetime', 'status', 'home_score_official', 'away_score_official']);

        $payload = $games->map(fn ($g) => [
            'id' => $g->id,
            'is_locked' => $g->lock_datetime <= $now,
            'status' => $g->status,
            'home_score_official' => $g->home_score_official,
            'away_score_official' => $g->away_score_official,
            'points_earned' => $g->userPrediction?->points_earned,
        ]);

        return response()->json([
            'now' => $now->toIso8601String(),
            'matches' => $payload,
        ]);
    }

    private function serializeGame(Game $game, \Illuminate\Support\Carbon $now): array
    {
        $prediction = $game->userPrediction;
        $isLocked = $game->lock_datetime <= $now;

        return [
            'id' => $game->id,
            'match_number' => $game->match_number,
            'phase' => $game->phase,
            'group_name' => $game->group_name,
            'home_team' => $game->home_team,
            'away_team' => $game->away_team,
            'home_flag' => $game->home_flag,
            'away_flag' => $game->away_flag,
            'venue' => $game->venue,
            'match_datetime' => $game->match_datetime->format('Y-m-d H:i'),
            'lock_datetime' => $game->lock_datetime->format('Y-m-d H:i'),
            'date_label' => $game->match_datetime->locale('es')->isoFormat('ddd D MMM, HH:mm'),
            'status' => $game->status,
            'is_locked' => $isLocked,
            'home_score_official' => $game->home_score_official,
            'away_score_official' => $game->away_score_official,
            'home_score' => $prediction?->home_score,
            'away_score' => $prediction?->away_score,
            'has_prediction' => $prediction !== null,
            'points_earned' => $prediction?->points_earned,
        ];
    }
}
