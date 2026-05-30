<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class FixtureGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function makeTournament(array $attrs = []): Tournament
    {
        $admin = User::factory()->create([
            'is_active' => true,
            'role'      => 'torneo_admin',
            'modules'   => 'torneos',
        ]);

        return Tournament::create(array_merge([
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
            'created_by_user_id'   => $admin->id,
        ], $attrs));
    }

    private function addApprovedTeams(Tournament $tournament, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos']);
            Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $captain->id,
                'name'            => 'Equipo ' . uniqid(),
                'status'          => 'approved',
            ]);
        }
    }

    private function service(): FixtureGeneratorService
    {
        return app(FixtureGeneratorService::class);
    }

    private function allMatchNumbers(Tournament $tournament): array
    {
        return TournamentMatch::whereHas('phase', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->pluck('match_number')->all();
    }

    // ─── round_robin ──────────────────────────────────────────────────────────

    public function test_round_robin_con_4_equipos_genera_6_partidos(): void
    {
        $tournament = $this->makeTournament([
            'format'        => 'round_robin',
            'groups_count'  => 1,
            'teams_per_group' => 4,
            'max_teams'     => 4,
        ]);
        $this->addApprovedTeams($tournament, 4);

        $summary = $this->service()->generate($tournament);

        $phase = $tournament->phases()->where('type', 'groups')->first();
        $this->assertEquals(1, $phase->groups()->count());
        $this->assertEquals(6, $phase->matches()->count()); // C(4,2) = 6
        $this->assertEquals(6, $summary['matches']);
    }

    // ─── groups_and_knockout ──────────────────────────────────────────────────

    public function test_groups_and_knockout_2x4_genera_12_de_grupos_mas_placeholders(): void
    {
        $tournament = $this->makeTournament(); // 2 grupos × 4 = 8 equipos
        $this->addApprovedTeams($tournament, 8);

        $this->service()->generate($tournament);

        $groupsPhase = $tournament->phases()->where('type', 'groups')->first();
        $this->assertEquals(2, $groupsPhase->groups()->count());
        $this->assertEquals(12, $groupsPhase->matches()->count()); // 2 × C(4,2) = 12

        // Avanzan 4 → Semifinal (2) + Final (1), placeholders sin equipos
        $knockout = $tournament->phases()->where('type', 'knockout')->orderBy('order')->get();
        $this->assertCount(2, $knockout);
        $this->assertEquals('Semifinal', $knockout[0]->name);
        $this->assertEquals(2, $knockout[0]->matches()->count());
        $this->assertEquals('Final', $knockout[1]->name);
        $this->assertNull($knockout[1]->matches()->first()->home_team_id);
    }

    // ─── knockout_only ─────────────────────────────────────────────────────────

    public function test_knockout_only_con_8_equipos_genera_4_partidos_primera_ronda(): void
    {
        $tournament = $this->makeTournament(['format' => 'knockout_only', 'max_teams' => 8]);
        $this->addApprovedTeams($tournament, 8);

        $this->service()->generate($tournament);

        $firstRound = $tournament->phases()->where('type', 'knockout')->orderBy('order')->first();
        $this->assertEquals('Cuartos de Final', $firstRound->name);
        $this->assertEquals(4, $firstRound->matches()->count());

        // La primera ronda tiene equipos asignados (no placeholders)
        $this->assertNotNull($firstRound->matches()->orderBy('match_number')->first()->home_team_id);
    }

    public function test_knockout_only_rechaza_cantidad_no_potencia_de_2(): void
    {
        $tournament = $this->makeTournament(['format' => 'knockout_only', 'max_teams' => 8]);
        $this->addApprovedTeams($tournament, 6); // no es potencia de 2

        $this->expectException(RuntimeException::class);
        $this->service()->generate($tournament);
    }

    // ─── validaciones ──────────────────────────────────────────────────────────

    public function test_no_genera_si_faltan_equipos_aprobados(): void
    {
        $tournament = $this->makeTournament(); // requiere 8
        $this->addApprovedTeams($tournament, 6);

        $this->expectException(RuntimeException::class);
        $this->service()->generate($tournament);
    }

    public function test_no_genera_fixture_dos_veces(): void
    {
        $tournament = $this->makeTournament();
        $this->addApprovedTeams($tournament, 8);

        $this->service()->generate($tournament);

        // Tras generar queda in_progress; reintentar debe fallar (ya no está open + ya hay fixture)
        $this->expectException(RuntimeException::class);
        $this->service()->generate($tournament->fresh());
    }

    public function test_no_genera_si_torneo_no_esta_open(): void
    {
        $tournament = $this->makeTournament(['status' => 'draft']);
        $this->addApprovedTeams($tournament, 8);

        $this->expectException(RuntimeException::class);
        $this->service()->generate($tournament);
    }

    // ─── atomicidad ─────────────────────────────────────────────────────────────

    public function test_si_falla_a_mitad_la_transaccion_revierte_todo(): void
    {
        $tournament = $this->makeTournament();
        $this->addApprovedTeams($tournament, 8);

        $faulty = new class extends FixtureGeneratorService {
            protected function buildKnockoutPhases(Tournament $tournament, int $startOrder, int $advancing): array
            {
                throw new RuntimeException('Fallo simulado a mitad de la generación.');
            }
        };

        try {
            $faulty->generate($tournament);
            $this->fail('Se esperaba una excepción.');
        } catch (RuntimeException $e) {
            // esperado
        }

        $this->assertEquals(0, $tournament->phases()->count());
        $this->assertEquals(0, count($this->allMatchNumbers($tournament)));
        $this->assertEquals('open', $tournament->fresh()->status); // status NO cambió
    }

    // ─── match_number global y único por torneo ──────────────────────────────────

    public function test_match_numbers_unicos_dentro_del_torneo(): void
    {
        $tournament = $this->makeTournament();
        $this->addApprovedTeams($tournament, 8);

        $this->service()->generate($tournament);

        $numbers = $this->allMatchNumbers($tournament);
        sort($numbers);

        $this->assertEquals(count($numbers), count(array_unique($numbers)), 'Hay match_number duplicados en el torneo.');
        $this->assertEquals(range(1, count($numbers)), $numbers, 'Los match_number no son secuenciales globales 1..N.');
    }

    // ─── status → in_progress ────────────────────────────────────────────────────

    public function test_el_torneo_pasa_a_in_progress_al_generar(): void
    {
        $tournament = $this->makeTournament();
        $this->addApprovedTeams($tournament, 8);

        $this->assertEquals('open', $tournament->status);
        $this->service()->generate($tournament);
        $this->assertEquals('in_progress', $tournament->fresh()->status);
    }

    // ─── comando Artisan ─────────────────────────────────────────────────────────

    public function test_comando_artisan_genera_fixture_con_resumen(): void
    {
        $tournament = $this->makeTournament();
        $this->addApprovedTeams($tournament, 8);

        $this->artisan('torneos:generate-fixture', ['tournament_id' => $tournament->id])
             ->assertExitCode(0);

        $this->assertEquals('in_progress', $tournament->fresh()->status);
        $this->assertTrue($tournament->phases()->exists());
    }

    public function test_comando_artisan_falla_con_torneo_inexistente(): void
    {
        $this->artisan('torneos:generate-fixture', ['tournament_id' => 99999])
             ->assertExitCode(1);
    }

    // ─── advanceTeams con cruce de llaves ────────────────────────────────────────

    public function test_advance_teams_asigna_clasificados_respetando_el_cruce(): void
    {
        $tournament = $this->makeTournament(); // 2 grupos, classifies 2
        $this->addApprovedTeams($tournament, 8);
        $this->service()->generate($tournament);

        $groupsPhase = $tournament->phases()->where('type', 'groups')->orderBy('order')->first();
        $groups = $groupsPhase->groups()->orderBy('order')->get();

        $a = $groups[0]->teams()->orderBy('teams.id')->pluck('teams.id')->all();
        $b = $groups[1]->teams()->orderBy('teams.id')->pluck('teams.id')->all();

        // Posiciones: A1, A2 / B1, B2
        $this->setStanding($groupsPhase->id, $groups[0]->id, $a[0], 1);
        $this->setStanding($groupsPhase->id, $groups[0]->id, $a[1], 2);
        $this->setStanding($groupsPhase->id, $groups[1]->id, $b[0], 1);
        $this->setStanding($groupsPhase->id, $groups[1]->id, $b[1], 2);

        $this->service()->advanceTeams($groupsPhase);

        $semifinal = $tournament->phases()->where('name', 'Semifinal')->first();
        $matches = $semifinal->matches()->orderBy('match_number')->get();

        // Cruce: partido 1 = A1 vs B2 ; partido 2 = B1 vs A2
        $this->assertEquals($a[0], $matches[0]->home_team_id);
        $this->assertEquals($b[1], $matches[0]->away_team_id);
        $this->assertEquals($b[0], $matches[1]->home_team_id);
        $this->assertEquals($a[1], $matches[1]->away_team_id);
    }

    private function setStanding(int $phaseId, int $groupId, int $teamId, int $position): void
    {
        Standing::create([
            'phase_id' => $phaseId,
            'group_id' => $groupId,
            'team_id'  => $teamId,
            'position' => $position,
            'points'   => 0,
        ]);
    }
}
