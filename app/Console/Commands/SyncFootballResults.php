<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\FootballDataService;
use App\Services\PredictionsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncFootballResults extends Command
{
    protected $signature = 'results:sync';

    protected $description = 'Sincroniza resultados desde football-data.org durante ventanas de partidos activos.';

    public function handle(FootballDataService $service): int
    {
        // PASO A — Detectar ventana activa
        $hayActivos = Game::where('status', 'live')
            ->whereNotNull('api_match_id')
            ->exists();

        $hayProximos = Game::where('status', 'upcoming')
            ->whereNotNull('api_match_id')
            ->where('match_datetime', '<=', now()->addMinutes(10))
            ->where('match_datetime', '>=', now()->subMinutes(130))
            ->exists();

        if (! $hayActivos && ! $hayProximos) {
            $this->info('Sin partidos en ventana activa. 0 requests.');

            return self::SUCCESS;
        }

        // PASO B — Obtener datos de la API (máx 2 requests)
        $matches = $service->fetchActiveAndRecentMatches();
        if (empty($matches)) {
            $this->warn('La API no devolvió datos en esta ejecución.');

            return self::SUCCESS;
        }

        $apiById = collect($matches)->keyBy(fn ($m) => (string) $m['id']);

        // PASO C — Partidos locales a procesar
        $games = Game::whereIn('status', ['upcoming', 'live'])
            ->whereNotNull('api_match_id')
            ->where(function ($q) {
                $q->where('match_datetime', '>=', now()->subHours(3))
                  ->orWhere('status', 'live');
            })
            ->get();

        // PASO D — Procesar cada partido
        foreach ($games as $game) {
            try {
                $apiMatch = $apiById->get((string) $game->api_match_id);
                if (! $apiMatch) {
                    continue;
                }

                $localStatus = $service->mapStatusToLocal($apiMatch['status']);
                if ($localStatus === null) {
                    continue;
                }

                if ($game->status !== $localStatus && $localStatus !== 'finished') {
                    $game->update(['status' => $localStatus]);
                }

                // Si terminó y NO tiene resultado manual del admin
                if ($localStatus === 'finished' && is_null($game->home_score_official)) {
                    $score = $service->extractScore($apiMatch);
                    if ($score === null) {
                        continue; // API aún sin marcador
                    }

                    DB::transaction(function () use ($game, $score) {
                        $game->update([
                            'home_score_official' => $score['home'],
                            'away_score_official' => $score['away'],
                            'status'              => 'finished',
                        ]);
                        app(PredictionsCalculator::class)->calculate($game->fresh());
                    });

                    $this->info("✓ {$game->home_team} {$score['home']}-{$score['away']} {$game->away_team}");
                    Log::info('Partido calculado automáticamente', [
                        'match_id'     => $game->id,
                        'match_number' => $game->match_number,
                        'score'        => $score,
                    ]);

                    continue;
                }

                // PRIORIDAD ADMIN: ya tiene resultado manual → no tocar
                if ($localStatus === 'finished' && ! is_null($game->home_score_official)) {
                    Log::debug('Partido ya calculado (manual), omitiendo', [
                        'match_id' => $game->id,
                    ]);
                }
            } catch (Throwable $e) {
                Log::error('results:sync error en partido, continuando', [
                    'match_id' => $game->id,
                    'message'  => $e->getMessage(),
                ]);

                continue;
            }
        }

        return self::SUCCESS;
    }
}
