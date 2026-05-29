<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FootballDataService
{
    private function httpClient(): PendingRequest
    {
        return Http::withHeaders([
            'X-Auth-Token' => config('services.football_data.token'),
        ])->timeout(15)
          ->baseUrl(config('services.football_data.base_url'));
    }

    private function competition(): string
    {
        return config('services.football_data.competition', 'WC');
    }

    /**
     * Trae los 104 partidos del torneo. Usado solo por el comando de mapeo (1 request).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchAllMatches(): array
    {
        try {
            $response = $this->httpClient()
                ->get("/competitions/{$this->competition()}/matches");

            if ($response->failed() || ! is_array($response->json('matches'))) {
                Log::error('football-data: fetchAllMatches respuesta inválida', [
                    'status' => $response->status(),
                ]);

                return [];
            }

            return $response->json('matches');
        } catch (Throwable $e) {
            Log::error('football-data: fetchAllMatches excepción', [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Combina partidos en juego + finalizados de hoy (máx 2 requests).
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchActiveAndRecentMatches(): array
    {
        $today = CarbonImmutable::now('America/Bogota')->format('Y-m-d');
        $comp = $this->competition();
        $merged = [];
        $anySuccess = false;

        try {
            $inPlay = $this->httpClient()->get("/competitions/{$comp}/matches", [
                'status' => 'IN_PLAY',
            ]);
            if ($inPlay->successful() && is_array($inPlay->json('matches'))) {
                $merged = array_merge($merged, $inPlay->json('matches'));
                $anySuccess = true;
            }
        } catch (Throwable $e) {
            Log::error('football-data: fetchActiveAndRecentMatches IN_PLAY excepción', [
                'message' => $e->getMessage(),
            ]);
        }

        try {
            $finished = $this->httpClient()->get("/competitions/{$comp}/matches", [
                'status'   => 'FINISHED',
                'dateFrom' => $today,
                'dateTo'   => $today,
            ]);
            if ($finished->successful() && is_array($finished->json('matches'))) {
                $merged = array_merge($merged, $finished->json('matches'));
                $anySuccess = true;
            }
        } catch (Throwable $e) {
            Log::error('football-data: fetchActiveAndRecentMatches FINISHED excepción', [
                'message' => $e->getMessage(),
            ]);
        }

        if (! $anySuccess) {
            Log::error('football-data: fetchActiveAndRecentMatches ambas llamadas fallaron');

            return [];
        }

        return $merged;
    }

    /**
     * Mapea el status de la API al status local. null = no actualizar.
     */
    public function mapStatusToLocal(string $apiStatus): ?string
    {
        return match ($apiStatus) {
            'TIMED', 'SCHEDULED'             => 'upcoming',
            'IN_PLAY', 'PAUSED', 'HALFTIME'  => 'live',
            'FINISHED'                       => 'finished',
            default                          => null,
        };
    }

    /**
     * Extrae el marcador de tiempo reglamentario. null si aún no hay marcador.
     *
     * @param  array<string,mixed>  $match
     * @return array{home:int,away:int}|null
     */
    public function extractScore(array $match): ?array
    {
        $home = $match['score']['fullTime']['home'] ?? null;
        $away = $match['score']['fullTime']['away'] ?? null;

        if (is_null($home) || is_null($away)) {
            return null;
        }

        return ['home' => (int) $home, 'away' => (int) $away];
    }
}
