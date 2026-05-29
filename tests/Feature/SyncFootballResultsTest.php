<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Prediction;
use App\Models\User;
use App\Services\PredictionsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class SyncFootballResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.football_data.token', 'test-token');
        Config::set('services.football_data.base_url', 'https://api.football-data.org/v4');
        Config::set('services.football_data.competition', 'WC');
    }

    private function liveGame(string $apiId, array $overrides = []): Game
    {
        return Game::create(array_merge([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9001,
            'home_team' => 'Mexico', 'away_team' => 'South Africa',
            'match_datetime' => now()->subMinutes(30),
            'lock_datetime' => now()->subMinutes(35),
            'status' => 'live',
            'api_match_id' => $apiId,
        ], $overrides));
    }

    private function fakeApi(array $matches): void
    {
        Http::fake([
            '*/competitions/WC/matches*' => Http::response(['matches' => $matches], 200),
        ]);
    }

    private function apiMatch(int $id, string $status, ?int $home, ?int $away): array
    {
        return [
            'id'       => $id,
            'status'   => $status,
            'stage'    => 'GROUP_STAGE',
            'homeTeam' => ['id' => 769, 'name' => 'Mexico'],
            'awayTeam' => ['id' => 774, 'name' => 'South Africa'],
            'score'    => ['fullTime' => ['home' => $home, 'away' => $away]],
        ];
    }

    public function test_sin_partidos_en_ventana_no_hace_requests(): void
    {
        Http::fake();

        $this->artisan('results:sync')
            ->expectsOutputToContain('Sin partidos en ventana activa')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_actualiza_a_finished_cuando_api_retorna_finished(): void
    {
        $game = $this->liveGame('537327');
        $this->fakeApi([$this->apiMatch(537327, 'FINISHED', 2, 0)]);

        $this->artisan('results:sync')->assertSuccessful();

        $this->assertDatabaseHas('matches', [
            'id' => $game->id, 'status' => 'finished',
            'home_score_official' => 2, 'away_score_official' => 0,
        ]);
    }

    public function test_no_sobrescribe_resultado_manual_del_admin(): void
    {
        $game = $this->liveGame('537327', [
            'home_score_official' => 3,
            'away_score_official' => 2,
        ]);
        $this->fakeApi([$this->apiMatch(537327, 'FINISHED', 0, 0)]);

        $this->artisan('results:sync')->assertSuccessful();

        $this->assertDatabaseHas('matches', [
            'id' => $game->id,
            'home_score_official' => 3,
            'away_score_official' => 2,
        ]);
    }

    public function test_dispara_calculo_de_puntos_al_terminar(): void
    {
        $game = $this->liveGame('537327');
        $user = User::factory()->create(['is_active' => true]);
        Prediction::create([
            'user_id' => $user->id, 'match_id' => $game->id,
            'home_score' => 2, 'away_score' => 0,
        ]);

        $this->fakeApi([$this->apiMatch(537327, 'FINISHED', 2, 0)]);

        $this->artisan('results:sync')->assertSuccessful();

        $this->assertSame(5, (int) Prediction::where('match_id', $game->id)->value('points_earned'));
        $this->assertDatabaseHas('rankings', ['user_id' => $user->id, 'total_points' => 5]);
    }

    public function test_si_calculo_falla_el_loop_continua(): void
    {
        $g1 = $this->liveGame('111', ['match_number' => 9001]);
        $g2 = $this->liveGame('222', ['match_number' => 9002, 'home_team' => 'Brazil', 'away_team' => 'Peru']);

        $this->fakeApi([
            $this->apiMatch(111, 'FINISHED', 1, 0),
            $this->apiMatch(222, 'FINISHED', 3, 1),
        ]);

        $real = app(PredictionsCalculator::class);
        $mock = Mockery::mock(PredictionsCalculator::class);
        $mock->shouldReceive('calculate')->andReturnUsing(function (Game $game) use ($real) {
            if ((int) $game->match_number === 9001) {
                throw new \RuntimeException('fallo simulado');
            }

            return $real->calculate($game);
        });
        $this->app->instance(PredictionsCalculator::class, $mock);

        $this->artisan('results:sync')->assertSuccessful();

        // g1: la transacción se revirtió, sigue sin resultado
        $this->assertDatabaseHas('matches', [
            'id' => $g1->id, 'home_score_official' => null,
        ]);
        // g2: procesado correctamente pese al fallo previo
        $this->assertDatabaseHas('matches', [
            'id' => $g2->id, 'status' => 'finished',
            'home_score_official' => 3, 'away_score_official' => 1,
        ]);
    }

    public function test_partido_postponed_es_ignorado(): void
    {
        $game = $this->liveGame('537327');
        $this->fakeApi([$this->apiMatch(537327, 'POSTPONED', null, null)]);

        $this->artisan('results:sync')->assertSuccessful();

        $this->assertDatabaseHas('matches', [
            'id' => $game->id, 'status' => 'live',
            'home_score_official' => null,
        ]);
    }
}
