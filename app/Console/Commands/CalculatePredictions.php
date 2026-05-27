<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\PredictionsCalculator;
use Illuminate\Console\Command;
use RuntimeException;

class CalculatePredictions extends Command
{
    protected $signature = 'predictions:calculate {match_id : ID del partido a calcular}';

    protected $description = 'Calcula points_earned de los pronósticos de un partido y recalcula el ranking completo.';

    public function handle(PredictionsCalculator $calculator): int
    {
        $matchId = (int) $this->argument('match_id');
        $game = Game::find($matchId);

        if (! $game) {
            $this->error("No existe el partido con id {$matchId}.");
            return self::FAILURE;
        }

        try {
            $r = $calculator->calculate($game);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Calculando puntos para partido #{$game->match_number}: {$game->home_team} {$game->home_score_official}-{$game->away_score_official} {$game->away_team}");
        $this->info("Pronósticos calculados: {$r['predictions_count']}");
        $this->line('Distribución de puntos:');
        $this->line(sprintf('  🥇 5 pts (exacto):           %d', $r['distribution'][5]));
        $this->line(sprintf('  🟢 3 pts (ganador+1 exacto): %d', $r['distribution'][3]));
        $this->line(sprintf('  🔵 2 pts (ganador):          %d', $r['distribution'][2]));
        $this->line(sprintf('  🟡 1 pt  (un n° en común):   %d', $r['distribution'][1]));
        $this->line(sprintf('  ⚫ 0 pts:                    %d', $r['distribution'][0]));
        $this->info("Ranking actualizado. {$r['ranking_count']} usuarios en la tabla.");

        if (count($r['top']) > 0) {
            $this->line('');
            $this->line('Top 5 actual:');
            foreach ($r['top'] as $row) {
                $this->line(sprintf(
                    '  %d. %-30s %4d pts  (%d exactos)',
                    $row['current_position'], $row['name'], $row['total_points'], $row['exact_predictions']
                ));
            }
        }

        return self::SUCCESS;
    }
}
