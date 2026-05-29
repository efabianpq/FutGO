<?php

namespace Tests\Unit;

use App\Services\FootballDataService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FootballDataServiceTest extends TestCase
{
    private FootballDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.football_data.token', 'test-token');
        Config::set('services.football_data.base_url', 'https://api.football-data.org/v4');
        Config::set('services.football_data.competition', 'WC');
        $this->service = new FootballDataService();
    }

    public function test_map_status_timed_es_upcoming(): void
    {
        $this->assertSame('upcoming', $this->service->mapStatusToLocal('TIMED'));
        $this->assertSame('upcoming', $this->service->mapStatusToLocal('SCHEDULED'));
    }

    public function test_map_status_finished_es_finished(): void
    {
        $this->assertSame('finished', $this->service->mapStatusToLocal('FINISHED'));
    }

    public function test_map_status_in_play_es_live(): void
    {
        $this->assertSame('live', $this->service->mapStatusToLocal('IN_PLAY'));
        $this->assertSame('live', $this->service->mapStatusToLocal('PAUSED'));
    }

    public function test_map_status_postponed_es_null(): void
    {
        $this->assertNull($this->service->mapStatusToLocal('POSTPONED'));
        $this->assertNull($this->service->mapStatusToLocal('CANCELLED'));
        $this->assertNull($this->service->mapStatusToLocal('SUSPENDED'));
        $this->assertNull($this->service->mapStatusToLocal('ABANDONED'));
    }

    public function test_extract_score_null_si_fulltime_home_es_null(): void
    {
        $match = ['score' => ['fullTime' => ['home' => null, 'away' => null]]];
        $this->assertNull($this->service->extractScore($match));
    }

    public function test_extract_score_retorna_valores_reales(): void
    {
        $match = ['score' => ['fullTime' => ['home' => 2, 'away' => 1]]];
        $this->assertSame(['home' => 2, 'away' => 1], $this->service->extractScore($match));
    }

    public function test_fetch_all_matches_retorna_vacio_en_error_500(): void
    {
        Http::fake([
            '*/competitions/WC/matches*' => Http::response('error', 500),
        ]);

        $this->assertSame([], $this->service->fetchAllMatches());
    }

    public function test_fetch_all_matches_retorna_vacio_si_falta_clave_matches(): void
    {
        Http::fake([
            '*/competitions/WC/matches*' => Http::response(['count' => 0], 200),
        ]);

        $this->assertSame([], $this->service->fetchAllMatches());
    }

    public function test_fetch_all_matches_retorna_partidos(): void
    {
        Http::fake([
            '*/competitions/WC/matches*' => Http::response([
                'matches' => [
                    ['id' => 537327, 'status' => 'TIMED'],
                ],
            ], 200),
        ]);

        $matches = $this->service->fetchAllMatches();
        $this->assertCount(1, $matches);
        $this->assertSame(537327, $matches[0]['id']);
    }
}
