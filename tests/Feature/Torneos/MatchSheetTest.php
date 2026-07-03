<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchSheetTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            [],
            $attrs
        ));
    }

    /** @return array{0:Tournament,1:User} [tournament, admin] */
    private function makeScenario(int $n = 4): array
    {
        $admin = $this->makeUser(['role' => 'user']);

        $tournament = Tournament::create([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => $n,
            'classifies_per_group' => 1,
            'max_teams'            => $n,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => 90,
            'category'             => 'libre',
            'visibility'           => 'public',
            'created_by_user_id'   => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        for ($i = 0; $i < $n; $i++) {
            $cap  = $this->makeUser(['name' => "Capitan{$i}"]);
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $cap->id,
                'name'            => "Equipo{$i}",
                'status'          => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'status' => 'active']);
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        return [$tournament, $admin];
    }

    private function firstMatch(Tournament $tournament): TournamentMatch
    {
        return TournamentMatch::whereHas('phase', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->orderBy('match_number')->first();
    }

    // ─── Autogeneración ──────────────────────────────────────────────────────

    public function test_planilla_nueva_no_premarca_jugadores(): void
    {
        [$tournament, $admin] = $this->makeScenario();
        $match = $this->firstMatch($tournament);

        $response = $this->actingAs($admin)
            ->get(route('admin.torneos.partidos.resultado', [$tournament, $match]))
            ->assertOk()
            ->assertSee('Planilla del Partido');

        // En una planilla nueva sin convocatoria previa cargada, nadie sale
        // marcado por defecto: quien diligencia la planilla decide jugó/titular.
        $response->assertDontSee('"played":true');
    }

    // ─── Observaciones arbitrales ──────────────────────────────────────────────

    public function test_planilla_persiste_observaciones_arbitrales(): void
    {
        [$tournament, $admin] = $this->makeScenario();
        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$tournament, $match]), [
            'home_score'    => 1,
            'away_score'    => 0,
            'referee_notes' => 'Expulsión del DT visitante por reclamos al minuto 40.',
        ])->assertRedirect();

        $this->assertSame(
            'Expulsión del DT visitante por reclamos al minuto 40.',
            $match->fresh()->referee_notes
        );
    }

    // ─── Fuente única de verdad ────────────────────────────────────────────────

    public function test_planilla_es_fuente_unica_actualiza_eventos_stats_standings(): void
    {
        [$tournament, $admin] = $this->makeScenario();
        $match  = $this->firstMatch($tournament);
        $player = Team::find($match->home_team_id)->players()->where('status', 'active')->first();

        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$tournament, $match]), [
            'home_score' => 2,
            'away_score' => 0,
            'lineups' => [
                ['team_player_id' => $player->id, 'team_id' => $match->home_team_id, 'started' => 1, 'minute_in' => 0, 'minute_out' => ''],
            ],
            'events' => [
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 12],
                ['team_player_id' => $player->id, 'type' => 'goal', 'minute' => 80],
            ],
        ])->assertRedirect();

        // Eventos
        $this->assertDatabaseCount('match_events', 2);
        // PlayerStats derivadas de la planilla
        $stat = PlayerStat::where('team_player_id', $player->id)->first();
        $this->assertNotNull($stat);
        $this->assertEquals(2, $stat->goals);
        $this->assertEquals(1, $stat->matches_played);
        // Standings derivados de la planilla
        $phase = $tournament->phases()->where('type', 'groups')->first();
        $standing = Standing::where('phase_id', $phase->id)->where('team_id', $match->home_team_id)->first();
        $this->assertEquals(3, $standing->points);
    }

    // ─── Documento finalizado ───────────────────────────────────────────────────

    public function test_planilla_finalizada_muestra_anular_para_editar(): void
    {
        [$tournament, $admin] = $this->makeScenario();
        $match = $this->firstMatch($tournament);

        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$tournament, $match]), [
            'home_score' => 0, 'away_score' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.torneos.partidos.resultado', [$tournament, $match->fresh()]))
            ->assertOk()
            ->assertSee('Anular para editar');
    }

    // ─── Exportación PDF ──────────────────────────────────────────────────────

    public function test_exportacion_pdf_de_la_planilla(): void
    {
        [$tournament, $admin] = $this->makeScenario();
        $match = $this->firstMatch($tournament);

        $response = $this->actingAs($admin)
            ->get(route('admin.torneos.partidos.pdf', [$tournament, $match]))
            ->assertOk();

        $this->assertStringContainsString('application/pdf', strtolower($response->headers->get('content-type') ?? ''));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_requiere_ser_admin_del_torneo(): void
    {
        [$tournament] = $this->makeScenario();
        $match = $this->firstMatch($tournament);

        $otherAdmin = $this->makeUser(['role' => 'user']);

        $this->actingAs($otherAdmin)
            ->get(route('admin.torneos.partidos.pdf', [$tournament, $match]))
            ->assertForbidden();
    }
}
