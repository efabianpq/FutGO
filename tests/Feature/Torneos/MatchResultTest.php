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
            'role'      => 'user',
        ]);
    }

    private function makeUser(): User
    {
        return User::factory()->create([
            'role'      => 'user',
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
            'home_score'     => 1,
            'away_score'     => 0,
            'referee'        => 'Carlos Árbitro',
            'second_referee' => 'Ana Asistente',
            'referee_notes'  => 'Sin incidencias',
            'sheet' => [
                'home' => ['delegate' => 'Del. Local', 'fouls_1' => 3, 'fouls_2' => 5, 'captain_signed' => '1'],
                'away' => ['delegate' => 'Del. Visita', 'fouls_1' => 2],
            ],
        ])->assertRedirect(route('admin.torneos.partidos.index', $tournament));

        $match->refresh();

        $this->assertSame('Carlos Árbitro', $match->referee);
        $this->assertSame('Ana Asistente', $match->second_referee);
        $this->assertSame('Sin incidencias', $match->referee_notes);

        $this->assertSame('Del. Local', $match->match_sheet['home']['delegate']);
        $this->assertSame(3, $match->match_sheet['home']['fouls_1']);
        $this->assertSame(5, $match->match_sheet['home']['fouls_2']);
        $this->assertTrue($match->match_sheet['home']['captain_signed']);
        $this->assertFalse($match->match_sheet['away']['captain_signed']);
        $this->assertSame('Del. Visita', $match->match_sheet['away']['delegate']);
    }

    public function test_anular_resultado_limpia_datos_de_planilla_pero_conserva_arbitros(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 0,
            'referee'    => 'Carlos Árbitro',
            'sheet'      => ['home' => ['fouls_1' => 4]],
        ]);

        $this->assertNotNull($match->fresh()->match_sheet);

        $this->actingAs($admin)->delete(route('admin.torneos.partidos.destroy', [$tournament, $match]));

        $match->refresh();
        $this->assertNull($match->match_sheet);
        // Los árbitros asignados se conservan tras anular el resultado.
        $this->assertSame('Carlos Árbitro', $match->referee);
    }

    public function test_marcador_consistente_con_goles_de_jugadores(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match    = $this->firstMatch($tournament);
        $homeTeam = Team::find($match->home_team_id);
        $player   = $homeTeam->players()->where('status', 'active')->first();

        // El formulario envía el marcador igual a la suma de goles de jugadores,
        // junto con un evento por cada gol (minuto opcional).
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2,
            'away_score' => 0,
            'lineups' => [
                ['team_player_id' => $player->id, 'team_id' => $match->home_team_id, 'started' => 1, 'minute_in' => 0, 'minute_out' => ''],
            ],
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 12],
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => null],
            ],
        ]);

        $match->refresh();
        $this->assertEquals(2, $match->home_score);
        $this->assertDatabaseCount('match_events', 2);
        // El segundo gol no trae minuto: se guarda como null (amateur).
        $this->assertDatabaseHas('match_events', [
            'match_id' => $match->id, 'type' => 'goal', 'minute' => null,
        ]);
    }

    public function test_partido_ganado_por_walkover(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score'      => 3,
            'away_score'      => 0,
            'is_walkover'     => 1,
            'walkover_winner' => 'home',
        ])->assertRedirect(route('admin.torneos.partidos.index', $tournament));

        $match->refresh();
        $this->assertTrue($match->is_walkover);
        $this->assertEquals('finished', $match->status);
        $this->assertEquals(3, $match->home_score);
        $this->assertEquals(0, $match->away_score);
        $this->assertEquals($match->home_team_id, $match->winner_team_id);

        // W.O. no carga goles ni convocatoria.
        $this->assertDatabaseCount('match_events', 0);
        $this->assertDatabaseCount('match_lineups', 0);

        // El ganador recibe los puntos de victoria en la tabla.
        $phase = $tournament->phases()->where('type', 'groups')->first();
        $standing = Standing::where('phase_id', $phase->id)
            ->where('team_id', $match->home_team_id)
            ->first();
        $this->assertEquals(3, $standing->points);
        $this->assertEquals(3, $standing->goals_for);
    }

    public function test_walkover_sin_equipo_ganador_falla(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament] = $this->setupRoundRobinWithFixture($admin, 4);

        $match = $this->firstMatch($tournament);

        $response = $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score'  => 0,
            'away_score'  => 0,
            'is_walkover' => 1,
            // sin walkover_winner
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals('scheduled', $match->fresh()->status);
    }

    // ─── H10: Estadísticas en tiempo real ────────────────────────────────────

    public function test_career_stats_se_actualizan_al_guardar_resultado(): void
    {
        $admin   = $this->makeTournamentAdmin();
        $captain = $this->makeUser();

        $tournament = Tournament::create([
            'name' => 'Copa Stats ' . uniqid(), 'slug' => 'copa-stats-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1,
            'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        // Equipo local con capitán como jugador
        $homeTeam = Team::create([
            'tournament_id' => $tournament->id, 'captain_user_id' => $captain->id,
            'name' => 'Local', 'status' => 'approved',
        ]);
        $tp = TeamPlayer::create([
            'team_id' => $homeTeam->id, 'user_id' => $captain->id, 'status' => 'active',
        ]);

        // Equipo visitante
        $awayUser = $this->makeUser();
        $awayTeam = Team::create([
            'tournament_id' => $tournament->id, 'captain_user_id' => $awayUser->id,
            'name' => 'Visitante', 'status' => 'approved',
        ]);
        TeamPlayer::create([
            'team_id' => $awayTeam->id, 'user_id' => $awayUser->id, 'status' => 'active',
        ]);

        app(\App\Services\Torneos\FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        $match = $this->firstMatch($tournament);

        // ANTES del resultado: career stats inexistentes o en cero
        $this->assertDatabaseMissing('player_career_stats', ['user_id' => $captain->id]);

        // Ingresar resultado con un gol del capitán
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 1,
            'away_score' => 0,
            'events' => [
                ['team_player_id' => $tp->id, 'type' => 'goal', 'minute' => 20],
            ],
        ]);

        // DESPUÉS del resultado: player_stats actualizado
        $ps = \App\Models\Torneos\PlayerStat::where('team_player_id', $tp->id)->first();
        $this->assertNotNull($ps);
        $this->assertEquals(1, $ps->goals);

        // Y career stats también actualizado en tiempo real
        $career = \App\Models\Torneos\PlayerCareerStat::where('user_id', $captain->id)->first();
        $this->assertNotNull($career, 'Los career stats deben existir inmediatamente después de guardar el resultado.');
        $this->assertEquals(1, $career->goals, 'Los goles del acumulado deben coincidir con los de la partida.');
    }

    public function test_career_stats_se_borran_al_eliminar_torneo_sin_finalizar(): void
    {
        $admin   = $this->makeTournamentAdmin();
        $captain = $this->makeUser();

        $tournament = Tournament::create([
            'name' => 'Copa Prueba ' . uniqid(), 'slug' => 'copa-prueba-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1,
            'max_teams' => 2, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $homeTeam = Team::create([
            'tournament_id' => $tournament->id, 'captain_user_id' => $captain->id,
            'name' => 'Local', 'status' => 'approved',
        ]);
        $tp = TeamPlayer::create([
            'team_id' => $homeTeam->id, 'user_id' => $captain->id, 'status' => 'active',
        ]);
        $awayUser = $this->makeUser();
        $awayTeam = Team::create([
            'tournament_id' => $tournament->id, 'captain_user_id' => $awayUser->id,
            'name' => 'Visitante', 'status' => 'approved',
        ]);
        TeamPlayer::create([
            'team_id' => $awayTeam->id, 'user_id' => $awayUser->id, 'status' => 'active',
        ]);

        app(\App\Services\Torneos\FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        $match = $this->firstMatch($tournament);

        // Ingresar resultado para generar career stats
        $this->actingAs($admin)->post($this->storeResultUrl($tournament, $match), [
            'home_score' => 2, 'away_score' => 0,
            'events' => [
                ['team_player_id' => $tp->id, 'type' => 'goal', 'minute' => 10],
                ['team_player_id' => $tp->id, 'type' => 'goal', 'minute' => 40],
            ],
        ]);

        // Confirmar que career stats existen con 2 goles
        $this->assertDatabaseHas('player_career_stats', ['user_id' => $captain->id, 'goals' => 2]);

        // Eliminar el torneo (en estado in_progress después de generate)
        // Para simular "open", ponemos el torneo de vuelta en open manualmente
        $tournament->status = 'open';
        $tournament->save();

        $this->actingAs($admin)->delete(route('admin.torneos.destroy', $tournament));

        // Los career stats deben haberse recalculado sin ese torneo → goals = 0
        $career = \App\Models\Torneos\PlayerCareerStat::where('user_id', $captain->id)->first();
        // Si el torneo fue el único, el career se recalcula a 0 (fila existe con 0 goals)
        if ($career) {
            $this->assertEquals(0, $career->goals, 'Los goles del acumulado deben ser 0 tras borrar el torneo de prueba.');
        } else {
            // Si no hay más torneos, la fila puede no existir (igualmente correcto)
            $this->assertTrue(true);
        }
    }

    // ─── Limitación #6: convocatoria previa pre-llena la alineación ────────────

    public function test_planilla_pre_marca_solo_a_los_jugadores_confirmados_en_la_convocatoria(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin, 2);
        $match = $this->firstMatch($tournament);

        $homeTeam = $teams[0];
        $homeCaptainPlayer = TeamPlayer::where('team_id', $homeTeam->id)->first();
        $confirmed = TeamPlayer::create(['team_id' => $homeTeam->id, 'full_name' => 'Confirmado', 'status' => 'active']);
        $declined  = TeamPlayer::create(['team_id' => $homeTeam->id, 'full_name' => 'Declinado', 'status' => 'active']);

        \App\Models\Torneos\MatchCallUp::create(['match_id' => $match->id, 'team_player_id' => $homeCaptainPlayer->id, 'team_id' => $homeTeam->id, 'status' => 'confirmado']);
        \App\Models\Torneos\MatchCallUp::create(['match_id' => $match->id, 'team_player_id' => $confirmed->id, 'team_id' => $homeTeam->id, 'status' => 'confirmado']);
        \App\Models\Torneos\MatchCallUp::create(['match_id' => $match->id, 'team_player_id' => $declined->id, 'team_id' => $homeTeam->id, 'status' => 'declinado']);

        $response = $this->actingAs($admin)->get(route('admin.torneos.partidos.resultado', [$tournament, $match]));
        $response->assertOk();

        $confirmedIds = $response->viewData('confirmedCallUpIds');
        $this->assertTrue($confirmedIds->contains($homeCaptainPlayer->id));
        $this->assertTrue($confirmedIds->contains($confirmed->id));
        $this->assertFalse($confirmedIds->contains($declined->id));

        $teamsWithCallUps = $response->viewData('teamsWithCallUps');
        $this->assertTrue($teamsWithCallUps->contains($homeTeam->id));
        $this->assertFalse($teamsWithCallUps->contains($teams[1]->id));

        // El equipo visitante (sin convocatoria cargada) mantiene el fallback: todos jugados.
        preg_match('/resultadoForm\((.*?)\)"/s', $response->getContent(), $m);
        $formInit = json_decode(html_entity_decode($m[1]), true);
        $players = collect($formInit['players']);

        $this->assertTrue($players->firstWhere('id', $homeCaptainPlayer->id)['played']);
        $this->assertTrue($players->firstWhere('id', $confirmed->id)['played']);
        $this->assertFalse($players->firstWhere('id', $declined->id)['played']);

        $awayCaptainPlayer = TeamPlayer::where('team_id', $teams[1]->id)->first();
        $this->assertTrue($players->firstWhere('id', $awayCaptainPlayer->id)['played']);
    }

    public function test_planilla_sin_convocatoria_previa_mantiene_todo_el_plantel_marcado(): void
    {
        $admin = $this->makeTournamentAdmin();
        [$tournament, $teams] = $this->setupRoundRobinWithFixture($admin, 2);
        $match = $this->firstMatch($tournament);

        $response = $this->actingAs($admin)->get(route('admin.torneos.partidos.resultado', [$tournament, $match]));
        $response->assertOk();

        $this->assertTrue($response->viewData('confirmedCallUpIds')->isEmpty());
        $this->assertTrue($response->viewData('teamsWithCallUps')->isEmpty());

        preg_match('/resultadoForm\((.*?)\)"/s', $response->getContent(), $m);
        $formInit = json_decode(html_entity_decode($m[1]), true);
        $players = collect($formInit['players']);

        $this->assertTrue($players->every(fn ($p) => $p['played'] === true));
    }
}
