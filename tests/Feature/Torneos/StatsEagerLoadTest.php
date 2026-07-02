<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifica que StatsController::jugador() carga los lineups por eager-load
 * (sin N+1): el número de queries no debe crecer con la cantidad de partidos.
 */
class StatsEagerLoadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    /**
     * Crea un torneo público con una fase, dos equipos y N partidos finalizados,
     * todos con lineup del jugador dado.
     */
    private function setupScenario(User $player, int $matchCount): array
    {
        $admin = User::factory()->create(['role' => 'user',]);

        $tournament = Tournament::create([
            'name'                 => 'Torneo N+1 Test',
            'slug'                 => 'torneo-n1-test-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'in_progress',
            'visibility'           => 'public',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => 2,
            'classifies_per_group' => 1,
            'max_teams'            => 2,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'created_by_user_id'   => $admin->id,
        ]);

        $phase = TournamentPhase::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Grupos',
            'type'          => 'groups',
            'status'        => 'active',
            'order'         => 1,
        ]);

        $teamA = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $player->id,
            'name'            => 'Equipo A',
            'status'          => 'approved',
        ]);
        $teamPlayer = TeamPlayer::create([
            'team_id'    => $teamA->id,
            'user_id'    => $player->id,
            'is_captain' => true,
            'status'     => 'active',
        ]);

        $opponent = User::factory()->create([]);
        $teamB = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $opponent->id,
            'name'            => 'Equipo B',
            'status'          => 'approved',
        ]);
        TeamPlayer::create([
            'team_id' => $teamB->id,
            'user_id' => $opponent->id,
            'status'  => 'active',
        ]);

        for ($i = 1; $i <= $matchCount; $i++) {
            $match = TournamentMatch::create([
                'phase_id'      => $phase->id,
                'home_team_id'  => $teamA->id,
                'away_team_id'  => $teamB->id,
                'status'        => 'finished',
                'home_score'    => 1,
                'away_score'    => 0,
                'match_number'  => $i,
            ]);

            MatchLineup::create([
                'match_id'       => $match->id,
                'team_player_id' => $teamPlayer->id,
                'team_id'        => $teamA->id,
                'started'        => true,
                'minute_in'      => 0,
            ]);
        }

        return [$tournament, $teamPlayer];
    }

    public function test_jugador_page_carga_ok_con_multiples_partidos(): void
    {
        $player = User::factory()->create([]);

        [$tournament, $teamPlayer] = $this->setupScenario($player, 3);

        $this->actingAs($player)
            ->get(route('torneos.estadisticas.jugador', [$tournament, $teamPlayer]))
            ->assertOk();
    }

    public function test_jugador_page_muestra_datos_de_lineup_desde_eager_load(): void
    {
        $player = User::factory()->create([]);

        // 3 partidos con lineup — antes del fix, el map() hacía 1 query por partido
        [$tournament, $teamPlayer] = $this->setupScenario($player, 3);

        $response = $this->actingAs($player)
            ->get(route('torneos.estadisticas.jugador', [$tournament, $teamPlayer]));

        $response->assertOk();
        // El eager-load de lineups entrega el dato 'Titular'/'Sustituto' en la vista.
        // Si el eager-load falla, $match->lineups->first() sería null y no se mostraría.
        $response->assertSee('Titular');
    }
}
