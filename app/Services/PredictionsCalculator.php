<?php

namespace App\Services;

use App\Models\Game;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PredictionsCalculator
{
    public function __construct(
        private readonly PredictionScoringService $scorer,
        private readonly RankingService $rankings,
    ) {}

    /**
     * Calcula puntos del partido y recalcula el ranking completo.
     *
     * @return array{
     *   match_number:int, predictions_count:int,
     *   distribution:array<int,int>, ranking_count:int,
     *   top:array<int,array{user_id:int,name:string,total_points:int,exact_predictions:int,current_position:int}>
     * }
     */
    public function calculate(Game $game): array
    {
        if ($game->home_score_official === null || $game->away_score_official === null) {
            throw new RuntimeException("El partido #{$game->match_number} no tiene resultado oficial cargado.");
        }

        $offHome = (int) $game->home_score_official;
        $offAway = (int) $game->away_score_official;

        $predictions = Prediction::where('match_id', $game->id)->get();
        $distribution = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 5 => 0];
        $now = now();

        DB::transaction(function () use ($predictions, $offHome, $offAway, &$distribution, $now) {
            foreach ($predictions as $p) {
                $pts = $this->scorer->calculate(
                    (int) $p->home_score,
                    (int) $p->away_score,
                    $offHome,
                    $offAway,
                );

                $p->points_earned = $pts;
                $p->updated_at = $now;
                $p->save();

                $distribution[$pts] = ($distribution[$pts] ?? 0) + 1;
            }
        });

        if ($game->status !== 'finished') {
            $game->update(['status' => 'finished']);
        }

        $rows = $this->rankings->recalculateAll();

        return [
            'match_number' => (int) $game->match_number,
            'predictions_count' => $predictions->count(),
            'distribution' => $distribution,
            'ranking_count' => count($rows),
            'top' => array_slice($rows, 0, 5),
        ];
    }
}
