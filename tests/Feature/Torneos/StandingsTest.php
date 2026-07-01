<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\StandingsCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandingsTest extends TestCase
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
     * Crea un torneo round_robin con N equipos y genera el fixture.
     * Retorna [Tournament, TeamCollection, TournamentPhase, TournamentGroup].
     */
    private function makeScenario(
        User $admin,
        int $n = 4,
        array $torneoAttrs = []
    ): array {
        $tournament = Tournament::create(array_merge([
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
            'created_by_user_id'   => $admin->id,
        ], $torneoAttrs));

        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = collect();
        for ($i = 0; $i < $n; $i++) {
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
        $group = $phase->groups()->first();

        return [$tournament, $teams, $phase, $group];
    }

    /** Marca un partido como finished con los scores dados. */
    private function finishMatch(TournamentMatch $match, int $homeScore, int $awayScore): void
    {
        $winner = match (true) {
            $homeScore > $awayScore => $match->home_team_id,
            $awayScore > $homeScore => $match->away_team_id,
            default                 => null,
        };

        $match->update([
            'home_score'     => $homeScore,
            'away_score'     => $awayScore,
            'winner_team_id' => $winner,
            'status'         => 'finished',
        ]);
    }

    private function service(): StandingsCalculatorService
    {
        return app(StandingsCalculatorService::class);
    }

    private function standingFor(mixed $phase, Team $team): ?Standing
    {
        return Standing::where('phase_id', $phase->id)
            ->where('team_id', $team->id)
            ->first();
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    public function test_victoria_suma_points_win(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3);

        $m = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $this->finishMatch($m, 2, 0);
        $this->service()->recalculate($phase);

        $winner = $this->standingFor($phase, Team::find($m->home_team_id));
        $this->assertEquals(3, $winner->points); // points_win = 3
        $this->assertEquals(1, $winner->won);
    }

    public function test_empate_suma_points_draw(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3);

        $m = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $this->finishMatch($m, 1, 1);
        $this->service()->recalculate($phase);

        $home = $this->standingFor($phase, Team::find($m->home_team_id));
        $away = $this->standingFor($phase, Team::find($m->away_team_id));
        $this->assertEquals(1, $home->points); // points_draw = 1
        $this->assertEquals(1, $away->points);
        $this->assertEquals(1, $home->drawn);
    }

    public function test_derrota_suma_points_loss(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3, ['points_loss' => 0]);

        $m = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $this->finishMatch($m, 3, 0);
        $this->service()->recalculate($phase);

        $loser = $this->standingFor($phase, Team::find($m->away_team_id));
        $this->assertEquals(0, $loser->points);
        $this->assertEquals(1, $loser->lost);
    }

    public function test_puntos_personalizados_no_hardcodeados(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3, [
            'points_win'  => 2,
            'points_draw' => 1,
            'points_loss' => 0,
        ]);

        $m = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $this->finishMatch($m, 1, 0);
        $this->service()->recalculate($phase);

        $winner = $this->standingFor($phase, Team::find($m->home_team_id));
        $this->assertEquals(2, $winner->points); // 2, nunca 3
    }

    public function test_diferencia_de_gol_correcta(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3);

        $m = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $this->finishMatch($m, 4, 1);
        $this->service()->recalculate($phase);

        $home = $this->standingFor($phase, Team::find($m->home_team_id));
        $away = $this->standingFor($phase, Team::find($m->away_team_id));
        $this->assertEquals(3,  $home->goal_difference);
        $this->assertEquals(-3, $away->goal_difference);
        $this->assertEquals(4,  $home->goals_for);
        $this->assertEquals(1,  $home->goals_against);
    }

    public function test_ordenamiento_por_puntos(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 4);

        $matches = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->get();
        $this->finishMatch($matches[0], 1, 0); // E0 gana, E1 pierde

        $this->service()->recalculate($phase);

        $winner = $this->standingFor($phase, Team::find($matches[0]->home_team_id));
        $loser  = $this->standingFor($phase, Team::find($matches[0]->away_team_id));
        $this->assertEquals(1, $winner->position);
        $this->assertLessThan($loser->position, $winner->position);
    }

    public function test_ordenamiento_por_diferencia_de_gol(): void
    {
        $admin = $this->makeAdmin();
        // 3 equipos: partidos E0vsE1, E0vsE2, E1vsE2
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3, [
            'tiebreaker_order' => ['goal_difference', 'goals_for'],
        ]);

        $matches = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->get();
        // E0 gana ambos con amplio margen → mayor DG
        $this->finishMatch($matches[0], 3, 0); // E0vsE1: E0 +3pts DG+3
        $this->finishMatch($matches[1], 2, 0); // E0vsE2: E0 +3pts DG+2
        $this->finishMatch($matches[2], 1, 0); // E1vsE2: E1 +3pts DG+0(-1 neto de ambos partidos)

        $this->service()->recalculate($phase);

        $s0 = $this->standingFor($phase, $teams[0]);
        $s1 = $this->standingFor($phase, $teams[1]);
        $s2 = $this->standingFor($phase, $teams[2]);

        // E0 primero (6pts), E1 segundo (3pts, DG=-2), E2 tercero (0pts)
        $this->assertEquals(1, $s0->position);
        $this->assertEquals(2, $s1->position);
        $this->assertEquals(3, $s2->position);
        // Verificar DG
        $this->assertEquals(5,  $s0->goal_difference);
        $this->assertEquals(-2, $s1->goal_difference);
        $this->assertEquals(-3, $s2->goal_difference);
    }

    public function test_ordenamiento_por_goles_a_favor(): void
    {
        $admin = $this->makeAdmin();
        // Escenario: E0 y E1 con misma DG pero distinto GF
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3, [
            'tiebreaker_order' => ['goal_difference', 'goals_for'],
        ]);

        $matches = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->get();
        $this->finishMatch($matches[0], 3, 0); // E0vsE1: E0 6pts total
        $this->finishMatch($matches[1], 2, 0); // E0vsE2
        $this->finishMatch($matches[2], 1, 0); // E1vsE2: E1 3pts

        $this->service()->recalculate($phase);

        // E0 tiene más puntos (6) → primero por puntos, no necesita DG/GF
        $s0 = $this->standingFor($phase, $teams[0]);
        $this->assertEquals(1, $s0->position);
        $this->assertEquals(5, $s0->goals_for); // 3+2=5
    }

    public function test_head_to_head_correcto(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 4, [
            'tiebreaker_order' => ['head_to_head', 'goal_difference'],
        ]);

        $matches = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->get();

        // Encontrar el partido directo entre E0 y E1
        $m01 = $matches->first(fn($m) =>
            ($m->home_team_id == $teams[0]->id && $m->away_team_id == $teams[1]->id) ||
            ($m->home_team_id == $teams[1]->id && $m->away_team_id == $teams[0]->id)
        );

        // E0 gana el enfrentamiento directo
        if ($m01->home_team_id == $teams[0]->id) {
            $this->finishMatch($m01, 1, 0);
        } else {
            $this->finishMatch($m01, 0, 1);
        }

        // Todos los demás partidos terminan 0-0 → mismos puntos globales para todos
        foreach ($matches as $m) {
            if ($m->id === $m01->id || $m->status === 'finished') continue;
            $this->finishMatch($m, 0, 0);
        }

        $this->service()->recalculate($phase);

        $s0 = $this->standingFor($phase, $teams[0]);
        $s1 = $this->standingFor($phase, $teams[1]);
        $this->assertLessThan($s1->position, $s0->position); // E0 antes que E1
    }

    public function test_solo_partidos_finished_afectan_standings(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3);

        $matches = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->get();
        $this->finishMatch($matches[0], 2, 0); // finished
        // matches[1] queda en 'scheduled' (no debe contar)

        $this->service()->recalculate($phase);

        $winner = $this->standingFor($phase, Team::find($matches[0]->home_team_id));
        $this->assertEquals(1, $winner->played);  // solo 1 partido contado
        $this->assertEquals(3, $winner->points);
    }

    public function test_recalcular_reemplaza_standings_anteriores(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3);

        $matches = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->get();

        // Primera pasada
        $this->finishMatch($matches[0], 2, 0);
        $this->service()->recalculate($phase);
        $countBefore = Standing::where('phase_id', $phase->id)->count();

        // Cambiar el resultado
        $matches[0]->update(['home_score' => 1, 'away_score' => 1, 'winner_team_id' => null, 'status' => 'finished']);
        $this->service()->recalculate($phase);
        $countAfter = Standing::where('phase_id', $phase->id)->count();

        $this->assertEquals($countBefore, $countAfter); // sin acumulación de filas
        $updated = $this->standingFor($phase, Team::find($matches[0]->home_team_id));
        $this->assertEquals(1, $updated->points); // empate = 1 pt, no el 3 anterior
    }

    public function test_recalcular_automatico_al_registrar_resultado_via_controller(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 4);

        $match = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();

        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            ['home_score' => 3, 'away_score' => 0]
        );

        $standing = $this->standingFor($phase, Team::find($match->home_team_id));
        $this->assertNotNull($standing);
        $this->assertEquals(3, $standing->points);
        $this->assertEquals(1, $standing->position);
    }

    public function test_vista_standings_admin_accesible_con_fixture(): void
    {
        $admin = $this->makeAdmin();
        [$tournament] = $this->makeScenario($admin, 4);

        $this->actingAs($admin)
            ->get(route('admin.torneos.standings.index', $tournament))
            ->assertOk()
            ->assertSee('Posiciones');
    }

    public function test_recalculate_manual_via_http(): void
    {
        $admin = $this->makeAdmin();
        [$tournament, $teams, $phase] = $this->makeScenario($admin, 3);

        $m = TournamentMatch::where('phase_id', $phase->id)->orderBy('match_number')->first();
        $this->finishMatch($m, 2, 0);

        $this->actingAs($admin)
            ->post(route('admin.torneos.standings.recalculate', $tournament))
            ->assertRedirect()
            ->assertSessionHas('status');

        $standing = $this->standingFor($phase, Team::find($m->home_team_id));
        $this->assertEquals(3, $standing->points);
    }
}
