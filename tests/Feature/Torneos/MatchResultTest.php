<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\Torneos\TournamentGroup;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchResultTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeTournamentAdmin(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'torneo_admin',
            'modules'   => 'torneos',
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ]);
    }

    /**
     * Crea un torneo round_robin con N equipos (cada uno con al menos 1 jugador activo)
     * y genera el fixture. Retorna el torneo listo con la fase de grupos.
     */
    private function setupRoundRobinWithFixture(User $admin, int $teamCount = 4): array
    {
        $tournament = Tournament::create([
            'name'                 => 'Copa Test ' . uniqid(),
            'slug'                 => 'copa-test-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => $teamCount,
            'classifies_per_group' => 1,
            'max_teams'            => $teamCount,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => 90,
            'created_by_user_id'   => $admin->id,
        ]);

        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $captain = $this->makeUser();
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $captain->id,
                'name'            => "Equipo $i",
                'status'          => 'approved',
            ]);
            TeamPlayer::create([
                'team_id' => $team->id,
                'user_id' => $captain->id,
                'status'  => 'active',
            ]);
            $teams[] = $team;
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        return [$tournament, $teams];
    }

    private function firstMatch(Tournament $tournament): TournamentMatch
    {
        return TournamentMatch::whereHas('phase', fn($q) => $q->where('tournament_id', $tournament->id))
            ->orderBy('match_number')
            ->first();
    }

    private function storeResultUrl(Tournament $tournament, TournamentMatch $match): string
    {
        return route('admin.torneos.partidos.store', [$tournament, $match]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_torneo_admin_puede_ingresar_resultado(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin);

        $match = $this->firstMatch($tournament);

        $response = $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 1,
        ]);

        $response->assertRedirect(route('admin.torneos.partidos.index', $tournament));
        $match->refresh();
        $this->assertEquals('finished', $match->status);
        $this->assertEquals(2, $match->home_score);
        $this->assertEquals(1, $match->away_score);
    }

    public function test_torneo_admin_de_otro_torneo_no_puede_ingresar_resultado(): void
    {
        $admin      = $this->makeTournamentAdmin();
        $otherAdmin = $this->makeTournamentAdmin();

        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin);
        $match = $this->firstMatch($tournament);

        $response = $this->actingAs($otherAdmin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $response->assertStatus(403);
    }

    public function test_al_guardar_resultado_el_partido_pasa_a_finished(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin);

        $match = $this->firstMatch($tournament);
        $this->assertEquals('scheduled', $match->status);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 0,
            'away_score' => 0,
        ]);

        $this->assertEquals('finished', $match->fresh()->status);
    }

    public function test_se_crean_los_match_events_correctamente(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin);

        $match   = $this->firstMatch($tournament);
        $homeTeam = Team::find($match->home_team_id);
        $player  = $homeTeam->players()->where('status', 'active')->first();

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 0,
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 23],
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 67],
            ],
        ]);

        $this->assertDatabaseCount('match_events', 2);
        $this->assertDatabaseHas('match_events', [
            'match_id'       => $match->id,
            'team_player_id' => $player->id,
            'type'           => 'goal',
            'minute'         => 23,
        ]);
    }

    public function test_los_standings_se_recalculan_despues_de_cada_resultado(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 3,
            'away_score' => 1,
        ]);

        $phase = $tournament->phases()->where('type', 'groups')->first();
        $this->assertDatabaseHas('standings', ['phase_id' => $phase->id]);

        $homeStanding = Standing::where('phase_id', $phase->id)
            ->where('team_id', $match->home_team_id)
            ->first();

        $this->assertNotNull($homeStanding);
        $this->assertEquals(3, $homeStanding->points);   // points_win = 3
        $this->assertEquals(3, $homeStanding->goals_for);
        $this->assertEquals(1, $homeStanding->goals_against);
    }

    public function test_las_player_stats_se_recalculan_despues_del_resultado(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin, 4);

        $match    = $this->firstMatch($tournament);
        $homeTeam = Team::find($match->home_team_id);
        $player   = $homeTeam->players()->where('status', 'active')->first();

        // A partir del Prompt 9, matches_played se calcula desde match_lineups.
        // Se debe incluir lineup junto con los eventos.
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 0,
            'lineups' => [
                [
                    'team_player_id' => $player->id,
                    'team_id'        => $match->home_team_id,
                    'started'        => 1,
                    'minute_in'      => 0,
                    'minute_out'     => '',
                ],
            ],
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 10],
                ['team_player_id' => $player->id, 'type' => 'yellow_card', 'minute' => 55],
            ],
        ]);

        $stat = PlayerStat::where('tournament_id', $tournament->id)
            ->where('team_player_id', $player->id)
            ->first();

        $this->assertNotNull($stat);
        $this->assertEquals(1, $stat->goals);
        $this->assertEquals(1, $stat->yellow_cards);
        $this->assertEquals(1, $stat->matches_played); // viene del lineup
        $this->assertEquals(1, $stat->wins);
    }

    public function test_se_puede_anular_un_resultado_y_los_datos_vuelven_al_estado_anterior(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin, 4);

        $match    = $this->firstMatch($tournament);
        $homeTeam = Team::find($match->home_team_id);
        $player   = $homeTeam->players()->where('status', 'active')->first();

        // Ingresar resultado con un evento
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 0,
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 30],
            ],
        ]);

        $this->assertEquals('finished', $match->fresh()->status);
        $this->assertDatabaseCount('match_events', 1);

        // Anular
        $this->actingAs($admin)->delete(
            route('admin.torneos.partidos.destroy', [$tournament, $match])
        );

        $match->refresh();
        $this->assertEquals('scheduled', $match->status);
        $this->assertNull($match->home_score);
        $this->assertNull($match->away_score);
        $this->assertDatabaseCount('match_events', 0);

        // Standings deben existir pero con ceros
        $phase = $tournament->phases()->where('type', 'groups')->first();
        $standing = Standing::where('phase_id', $phase->id)
            ->where('team_id', $match->home_team_id)
            ->first();
        $this->assertNotNull($standing);
        $this->assertEquals(0, $standing->points);
    }

    public function test_no_se_puede_ingresar_resultado_de_partido_ya_finished_sin_anular(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin);

        $match = $this->firstMatch($tournament);
        $match->update(['status' => 'finished', 'home_score' => 1, 'away_score' => 0]);

        $response = $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 1,
        ]);

        // Redirige de vuelta con error
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_los_puntos_se_calculan_con_los_valores_del_torneo(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin, 4);

        // Cambiar a points_win=2
        $tournament->update(['points_win' => 2, 'points_draw' => 1, 'points_loss' => 0]);

        $match = $this->firstMatch($tournament);
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 1,
            'away_score' => 0,
        ]);

        $phase = $tournament->phases()->where('type', 'groups')->first();
        $homeStanding = Standing::where('phase_id', $phase->id)
            ->where('team_id', $match->home_team_id)
            ->first();

        $this->assertEquals(2, $homeStanding->points);  // points_win=2
    }

    public function test_torneo_con_points_win_2_calcula_correctamente(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $tournament->update(['points_win' => 2, 'points_draw' => 1, 'points_loss' => 0]);

        $m1 = $this->firstMatch($tournament);

        // Equipo local gana: debe recibir exactamente 2 puntos (points_win=2)
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $m1), [
            'home_score' => 3, 'away_score' => 0,
        ]);

        $phase = $tournament->phases()->where('type', 'groups')->first();

        $winner = Standing::where('phase_id', $phase->id)
            ->where('team_id', $m1->home_team_id)
            ->first();

        $this->assertNotNull($winner);
        $this->assertEquals(2, $winner->points);  // points_win=2, no hardcodeado 3
        $this->assertEquals(0, Standing::where('phase_id', $phase->id)
            ->where('team_id', $m1->away_team_id)
            ->value('points'));
    }

    public function test_marcar_partido_en_vivo(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin);

        $match = $this->firstMatch($tournament);
        $this->assertEquals('scheduled', $match->status);

        $this->actingAs($admin)->patch(
            route('admin.torneos.partidos.live', [$tournament, $match])
        );

        $this->assertEquals('live', $match->fresh()->status);
    }

    public function test_partido_live_acepta_resultado(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin);

        $match = $this->firstMatch($tournament);
        $match->update(['status' => 'live']);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 3,
            'away_score' => 2,
        ]);

        $this->assertEquals('finished', $match->fresh()->status);
    }

    public function test_eventos_previos_se_eliminan_en_reingreso(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin, 4);

        $match    = $this->firstMatch($tournament);
        $homeTeam = Team::find($match->home_team_id);
        $player   = $homeTeam->players()->where('status', 'active')->first();

        // Primer ingreso: 2 eventos
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2, 'away_score' => 0,
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 10],
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 55],
            ],
        ]);

        $this->assertDatabaseCount('match_events', 2);

        // Anular y re-ingresar con 1 evento
        $this->actingAs($admin)->delete(route('admin.torneos.partidos.destroy', [$tournament, $match]));

        $match->refresh();
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 1, 'away_score' => 0,
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 30],
            ],
        ]);

        $this->assertDatabaseCount('match_events', 1);
    }

    public function test_se_guarda_la_planilla_oficial_completa(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score'     => 3,
            'away_score'     => 2,
            'referee'        => 'Carlos Árbitro',
            'second_referee' => 'Ana Asistente',
            'timekeeper'     => 'Mesa Uno',
            'coordinator'    => 'Coord. Zona',
            'home_score_ht'  => 1,
            'away_score_ht'  => 1,
            'home_penalties' => 4,
            'away_penalties' => 2,
            'sheet' => [
                'home' => ['coach' => 'Profe Juan', 'delegate' => 'Del. Local', 'fouls_1' => 3, 'fouls_2' => 5, 'timeouts' => 1, 'captain_signed' => '1'],
                'away' => ['coach' => 'Profe Pedro', 'fouls_1' => 2],
            ],
        ])->assertRedirect(route('admin.torneos.partidos.index', $tournament));

        $match->refresh();

        $this->assertSame('Carlos Árbitro', $match->referee);
        $this->assertSame('Ana Asistente', $match->second_referee);
        $this->assertSame('Mesa Uno', $match->timekeeper);
        $this->assertEquals(1, $match->home_score_ht);
        $this->assertEquals(4, $match->home_penalties);

        $this->assertSame('Profe Juan', $match->match_sheet['home']['coach']);
        $this->assertSame(3, $match->match_sheet['home']['fouls_1']);
        $this->assertTrue($match->match_sheet['home']['captain_signed']);
        $this->assertFalse($match->match_sheet['away']['captain_signed']);
        $this->assertSame('Profe Pedro', $match->match_sheet['away']['coach']);
    }

    public function test_anular_resultado_limpia_datos_de_planilla_pero_conserva_arbitros(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score'    => 2,
            'away_score'    => 0,
            'referee'       => 'Carlos Árbitro',
            'home_score_ht' => 1,
            'sheet'         => ['home' => ['fouls_1' => 4]],
        ]);

        $this->actingAs($admin)->delete(route('admin.torneos.partidos.destroy', [$tournament, $match]));

        $match->refresh();
        $this->assertNull($match->home_score_ht);
        $this->assertNull($match->match_sheet);
        // Los árbitros asignados se conservan tras anular el resultado.
        $this->assertSame('Carlos Árbitro', $match->referee);
    }
}
