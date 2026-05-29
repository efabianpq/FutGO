<?php

namespace Tests\Feature;

use App\Console\Commands\MapFootballDataIds;
use App\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapFootballDataIdsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.football_data.token', 'test-token');
        Config::set('services.football_data.base_url', 'https://api.football-data.org/v4');
        Config::set('services.football_data.competition', 'WC');
    }

    private function groupGame(): Game
    {
        return Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 1,
            'home_team' => 'Mexico', 'away_team' => 'South Africa',
            'match_datetime' => '2026-06-11 14:00:00',
            'lock_datetime' => '2026-06-11 13:55:00',
            'status' => 'upcoming',
        ]);
    }

    private function fakeApi(): void
    {
        Http::fake([
            '*/competitions/WC/matches*' => Http::response([
                'matches' => [
                    [
                        'id'       => 537327,
                        'utcDate'  => '2026-06-11T19:00:00Z',
                        'status'   => 'TIMED',
                        'stage'    => 'GROUP_STAGE',
                        'group'    => 'GROUP_A',
                        'homeTeam' => ['id' => 769, 'name' => 'Mexico'],
                        'awayTeam' => ['id' => 774, 'name' => 'South Africa'],
                        'score'    => ['fullTime' => ['home' => null, 'away' => null]],
                    ],
                ],
            ], 200),
        ]);
    }

    public function test_normaliza_cote_divoire(): void
    {
        $cmd = new MapFootballDataIds();
        $this->assertSame('cote divoire', $cmd->normalizeTeamName("Côte d'Ivoire"));
    }

    public function test_normaliza_bosnia_herzegovina(): void
    {
        $cmd = new MapFootballDataIds();
        $this->assertSame('bosniaherzegovina', $cmd->normalizeTeamName('Bosnia-Herzegovina'));
    }

    public function test_dry_run_no_guarda_nada(): void
    {
        $game = $this->groupGame();
        $this->fakeApi();

        $this->artisan('fixtures:map-ids', ['--dry-run' => true])->assertSuccessful();

        $this->assertNull($game->fresh()->api_match_id);
    }

    public function test_matching_exacto_guarda_api_match_id(): void
    {
        $game = $this->groupGame();
        $this->fakeApi();

        $this->artisan('fixtures:map-ids')->assertSuccessful();

        $this->assertSame('537327', $game->fresh()->api_match_id);
    }
}
