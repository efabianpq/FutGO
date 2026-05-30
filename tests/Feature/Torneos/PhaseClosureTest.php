<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PhaseClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PhaseClosureTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::factory()->create([
            'is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos',
        ]);
    }

    /**
     * Torneo groups_and_knockout: 2 grupos × 4 = 8 equipos, classifies 2.
     * Genera el fixture (queda in_progress).
     *
     * @return array{0:Tournament, 1:\Illuminate\Support\Collection, 2:TournamentPhase}
     */
    private function makeScenario(User $admin, array $attrs = []): array
    {
        $tournament = Tournament::create(array_merge([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            'max_teams'            => 8,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => 90,
            'created_by_user_id'   => $admin->id,
        ], $attrs));

        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = collect();
        for ($i = 0; $i < 8; $i++) {
            $cap = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $cap->id,
                'name'            => "Equipo$i",
                'status'          => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'status' => 'active']);
            $teams->push($team);
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        $phase = $tournament->phases()->where('type', 'groups')->first();

        return [$tournament, $teams, $phase];
    }

    /** Marca un partido como finished con winner deducido del marcador. */
    private function finishMatch(TournamentMatch $match, int $home, int $away): void
    {
        $winner = match (true) {
            $home > $away => $match->home_team_id,
            $away > $home => $match->away_team_id,
            default       => null,
        };
        $match->update([
            'home_score' => $home, 'away_score' => $away,
            'winner_team_id' => $winner, 'status' => 'finished',
        ]);
    }

    /** Finaliza TODOS los partidos de grupos de la fase (marcador trivial). */
    private function finishAllGroupMatches(TournamentPhase $phase): void
    {
        foreach ($phase->matches()->whereNotNull('home_team_id')->get() as $m) {
            $this->finishMatch($m, 1, 0);
        }
    }

    /** Fija manualmente las posiciones de standings de un grupo (control de cruce). */
    private function setStanding(TournamentPhase $phase, int $groupId, int $teamId, int $position): void
    {
        Standing::create([
            'phase_id' => $phase->id,
            'group_id' => $groupId,
            'team_id'  => $teamId,
            'position' => $position,
            'points'   => 0,
        ]);
    }

    /**
     * Asigna posiciones 1..N por grupo en orden de teams.id (determinista),
     * para que advanceTeams pueda leer clasificados.
     *
     * @return array{0:array<int,int>,1:array<int,int>} ids ordenados por posición de cada grupo
     */
    private function seedStandings(TournamentPhase $phase): array
    {
        $groups = $phase->groups()->orderBy('order')->get();
        $result = [];
        foreach ($groups as $gIndex => $group) {
            $ids = $group->teams()->orderBy('teams.id')->pluck('teams.id')->all();
            foreach ($ids as $pos => $teamId) {
                $this->setStanding($phase, $group->id, $teamId, $pos + 1);
            }
            $result[$gIndex] = $ids;
        }
        return $result;
    }

    private function service(): PhaseClosureService
    {
        return app(PhaseClosureService::class);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_no_cierra_con_partidos_pendientes(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        // Finalizamos todos menos uno
        $matches = $phase->matches()->whereNotNull('home_team_id')->orderBy('match_number')->get();
        foreach ($matches->skip(1) as $m) {
            $this->finishMatch($m, 1, 0);
        }
        $this->seedStandings($phase);

        $this->expectException(RuntimeException::class);
        $this->service()->closeGroupPhase($phase);
    }

    public function test_cierra_cuando_todos_finalizados(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);

        $closed = $this->service()->closeGroupPhase($phase);

        $this->assertEquals('completed', $closed->status);
        $this->assertFalse((bool) $closed->is_active);
    }

    public function test_marca_la_fase_como_completed(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);
        $this->service()->closeGroupPhase($phase);

        $this->assertTrue($phase->fresh()->isCompleted());
    }

    public function test_clasifica_segun_classifies_per_group(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        [$a, $b] = $this->seedStandings($phase);

        $this->service()->closeGroupPhase($phase);

        // Clasifican los 2 primeros de cada grupo: a[0],a[1],b[0],b[1]
        $semifinal = $tournament->phases()->where('name', 'Semifinal')->first();
        $assigned = $semifinal->matches()
            ->get()
            ->flatMap(fn ($m) => [$m->home_team_id, $m->away_team_id])
            ->filter()
            ->sort()
            ->values()
            ->all();

        $expected = collect([$a[0], $a[1], $b[0], $b[1]])->sort()->values()->all();
        $this->assertEquals($expected, $assigned);
    }

    public function test_genera_la_siguiente_ronda_activa(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);
        $this->service()->closeGroupPhase($phase);

        $semifinal = $tournament->phases()->where('name', 'Semifinal')->first();
        $this->assertTrue((bool) $semifinal->fresh()->is_active);
        $this->assertEquals('active', $semifinal->fresh()->status);
        // Ambos partidos quedaron con equipos asignados
        $this->assertEquals(0, $semifinal->matches()->whereNull('home_team_id')->count());
    }

    public function test_crea_los_cruces_esperados(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        [$a, $b] = $this->seedStandings($phase);

        $this->service()->closeGroupPhase($phase);

        $semifinal = $tournament->phases()->where('name', 'Semifinal')->first();
        $sf = $semifinal->matches()->orderBy('match_number')->get();

        // Cruce estándar: A1 vs B2 ; B1 vs A2
        $this->assertEquals($a[0], $sf[0]->home_team_id);
        $this->assertEquals($b[1], $sf[0]->away_team_id);
        $this->assertEquals($b[0], $sf[1]->home_team_id);
        $this->assertEquals($a[1], $sf[1]->away_team_id);
    }

    public function test_no_permite_cerrar_dos_veces(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);
        $this->service()->closeGroupPhase($phase);

        $this->expectException(RuntimeException::class);
        $this->service()->closeGroupPhase($phase->fresh());
    }

    public function test_no_permite_modificar_resultados_despues_del_cierre(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);
        $this->service()->closeGroupPhase($phase);

        $match = $phase->matches()->whereNotNull('home_team_id')->orderBy('match_number')->first();

        $this->actingAs($admin)
            ->post(route('admin.torneos.partidos.store', [$tournament, $match]),
                ['home_score' => 5, 'away_score' => 5])
            ->assertSessionHas('error');

        // El marcador original (1-0) no cambió
        $match->refresh();
        $this->assertEquals(1, $match->home_score);
        $this->assertEquals(0, $match->away_score);
    }

    public function test_no_recalcula_standings_de_fase_cerrada(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);
        $this->service()->closeGroupPhase($phase);

        // El recálculo manual no debe tocar una fase cerrada.
        $this->actingAs($admin)
            ->post(route('admin.torneos.standings.recalculate', $tournament))
            ->assertSessionHas('error');
    }

    public function test_maneja_tercer_puesto_habilitado(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, ['third_place_match' => true]);

        // La generación del fixture creó la fase de tercer puesto.
        $thirdPlace = $tournament->phases()->where('type', 'third_place')->first();
        $this->assertNotNull($thirdPlace, 'Debe existir fase de tercer puesto.');

        $this->finishAllGroupMatches($phase);
        [$a, $b] = $this->seedStandings($phase);

        $this->service()->closeGroupPhase($phase);

        // El cierre avanza a Semifinal (no al tercer puesto, que queda placeholder).
        $semifinal = $tournament->phases()->where('name', 'Semifinal')->first();
        $sf = $semifinal->matches()->orderBy('match_number')->get();
        $this->assertEquals($a[0], $sf[0]->home_team_id);
        $this->assertEquals($b[1], $sf[0]->away_team_id);

        // El tercer puesto sigue sin equipos (se llena al cerrar la semifinal).
        $this->assertNull($thirdPlace->matches()->first()->home_team_id);
    }

    public function test_pantalla_de_cierre_accesible(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);

        $this->actingAs($admin)
            ->get(route('admin.torneos.phases.close', [$tournament, $phase]))
            ->assertOk()
            ->assertSee('Cerrar fase');
    }

    public function test_cierre_via_http_genera_eliminatoria(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin);

        $this->finishAllGroupMatches($phase);
        $this->seedStandings($phase);

        $this->actingAs($admin)
            ->post(route('admin.torneos.phases.close.execute', [$tournament, $phase]))
            ->assertRedirect(route('admin.torneos.show', $tournament))
            ->assertSessionHas('status');

        $this->assertTrue($phase->fresh()->isCompleted());
    }
}
