<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Prediction;
use App\Services\PredictionScoringService;
use App\Services\RankingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculatePredictions extends Command
{
    protected $signature = 'predictions:calculate {match_id : ID del partido a calcular}';

    protected $description = 'Calcula points_earned de los pronósticos de un partido y recalcula el ranking completo.';

    public function handle(PredictionScoringService $scorer, RankingService $rankingService): int
    {
        $matchId = (int) $this->argument('match_id');
        $game = Game::find($matchId);

        if (! $game) {
            $this->error("No existe el partido con id {$matchId}.");
            return self::FAILURE;
        }

        if ($game->home_score_official === null || $game->away_score_official === null) {
            $this->error("El partido #{$game->match_number} no tiene resultado oficial cargado.");
            return self::FAILURE;
        }

        $offHome = (int) $game->home_score_official;
        $offAway = (int) $game->away_score_official;

        $this->info("Calculando puntos para partido #{$game->match_number}: {$game->home_team} {$offHome}-{$offAway} {$game->away_team}");

        $predictions = Prediction::where('match_id', $game->id)->get();

        $distribution = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 5 => 0];
        $now = now();

        DB::transaction(function () use ($predictions, $scorer, $offHome, $offAway, &$distribution, $now) {
            foreach ($predictions as $p) {
                $pts = $scorer->calculate(
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

        $this->info("Pronósticos calculados: {$predictions->count()}");
        $this->line('Distribución de puntos:');
        $this->line(sprintf('  🥇 5 pts (exacto):           %d', $distribution[5]));
        $this->line(sprintf('  🟢 3 pts (ganador+1 exacto): %d', $distribution[3]));
        $this->line(sprintf('  🔵 2 pts (ganador):          %d', $distribution[2]));
        $this->line(sprintf('  🟡 1 pt  (un n° en común):   %d', $distribution[1]));
        $this->line(sprintf('  ⚫ 0 pts:                    %d', $distribution[0]));

        $this->info('Recalculando ranking…');
        $rows = $rankingService->recalculateAll();
        $this->info(sprintf('Ranking actualizado. %d usuarios en la tabla.', count($rows)));

        if (count($rows) > 0) {
            $this->line('');
            $this->line('Top 5 actual:');
            foreach (array_slice($rows, 0, 5) as $r) {
                $this->line(sprintf(
                    '  %d. %-30s %4d pts  (%d exactos)',
                    $r['current_position'], $r['name'], $r['total_points'], $r['exact_predictions']
                ));
            }
        }

        return self::SUCCESS;
    }
}
