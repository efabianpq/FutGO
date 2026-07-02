<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Progresión automática de la eliminatoria: al finalizar una ronda, los ganadores
 * avanzan a la siguiente, los perdedores de semifinal pueblan el tercer puesto, y
 * al cerrar la final el torneo queda 'finished'.
 */
class KnockoutProgressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'user',]);
    }

    /** Torneo knockout_only de 4 equipos con tercer puesto y fixture generado. */
    private function makeKnockout(User $admin): Tournament
    {
        return $this->makeKnockoutOf($admin, 4);
    }

    /** Torneo knockout_only de $teamCount equipos (potencia de 2) con tercer puesto. */
    private function makeKnockoutOf(User $admin, int $teamCount): Tournament
    {
        $t = Tournament::create([
            'name' => 'Copa KO ' . uniqid(), 'slug' => 'ko-' . uniqid(), 'sport' => 'futbol',
            'status' => 'open', 'format' => 'knockout_only', 'groups_count' => 1, 'teams_per_group' => $teamCount,
            'classifies_per_group' => 1, 'max_teams' => $teamCount, 'third_place_match' => true,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'match_duration' => 90,
            'category' => 'libre', 'visibility' => 'public', 'created_by_user_id' => $admin->id,
        ]);
        $t->tournamentAdmins()->create(['user_id' => $admin->id]);

        for ($i = 0; $i < $teamCount; $i++) {
            $cap = User::factory()->create(['name' => "Cap $i"]);
            $team = Team::create(['tournament_id' => $t->id, 'captain_user_id' => $cap->id, 'name' => "Equipo $i", 'status' => 'approved']);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'status' => 'active']);
        }

        app(FixtureGeneratorService::class)->generate($t);

        return $t->fresh();
    }

    private function storeResult(User $admin, Tournament $t, TournamentMatch $m, array $data): void
    {
        $this->actingAs($admin)->post(route('admin.torneos.partidos.store', [$t, $m]), $data)->assertRedirect();
    }

    private function destroyResult(User $admin, Tournament $t, TournamentMatch $m): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($admin)->delete(route('admin.torneos.partidos.destroy', [$t, $m]));
    }

    public function test_semifinal_avanza_ganadores_a_final_y_perdedores_a_tercer_puesto(): void
    {
        $admin = $this->admin();
        $t = $this->makeKnockout($admin);

        $semis = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Semifinal')->first();
        $semiMatches = TournamentMatch::where('phase_id', $semis->id)->orderBy('match_number')->get();
        $this->assertCount(2, $semiMatches);

        // Semifinal 1: gana local por marcador.
        $this->storeResult($admin, $t, $semiMatches[0], ['home_score' => 2, 'away_score' => 0]);
        // Semifinal 2: empate definido por penales (gana local).
        $this->storeResult($admin, $t, $semiMatches[1], ['home_score' => 1, 'away_score' => 1, 'home_penalties' => 5, 'away_penalties' => 4]);

        $final = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Final')->first();
        $finalMatch = TournamentMatch::where('phase_id', $final->id)->first();
        $third = TournamentPhase::where('tournament_id', $t->id)->where('type', 'third_place')->first();
        $thirdMatch = TournamentMatch::where('phase_id', $third->id)->first();

        // La final quedó poblada con los dos ganadores.
        $this->assertNotNull($finalMatch->home_team_id);
        $this->assertNotNull($finalMatch->away_team_id);
        $expectedFinalists = [$semiMatches[0]->fresh()->winner_team_id, $semiMatches[1]->fresh()->winner_team_id];
        $this->assertEqualsCanonicalizing($expectedFinalists, [$finalMatch->home_team_id, $finalMatch->away_team_id]);

        // El tercer puesto quedó poblado con los dos perdedores.
        $this->assertNotNull($thirdMatch->home_team_id);
        $this->assertNotNull($thirdMatch->away_team_id);

        // Las fases siguientes quedaron activas.
        $this->assertTrue($final->fresh()->is_active);
    }

    public function test_cerrar_la_final_marca_el_torneo_finalizado(): void
    {
        $admin = $this->admin();
        $t = $this->makeKnockout($admin);

        $semis = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Semifinal')->first();
        $semiMatches = TournamentMatch::where('phase_id', $semis->id)->orderBy('match_number')->get();
        $this->storeResult($admin, $t, $semiMatches[0], ['home_score' => 2, 'away_score' => 0]);
        $this->storeResult($admin, $t, $semiMatches[1], ['home_score' => 3, 'away_score' => 1]);

        $finalMatch = TournamentMatch::where('phase_id',
            TournamentPhase::where('tournament_id', $t->id)->where('name', 'Final')->value('id')
        )->first();

        $this->assertSame('in_progress', $t->fresh()->status);

        $this->storeResult($admin, $t, $finalMatch, ['home_score' => 2, 'away_score' => 1]);

        $this->assertSame('finished', $t->fresh()->status);
        $this->assertNotNull($finalMatch->fresh()->winner_team_id);
    }

    public function test_no_se_puede_anular_cuartos_si_semifinal_ya_tiene_equipo_asignado(): void
    {
        $admin = $this->admin();
        $t = $this->makeKnockoutOf($admin, 8);

        $cuartos = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Cuartos de Final')->first();
        $matches = TournamentMatch::where('phase_id', $cuartos->id)->orderBy('match_number')->get();
        $this->assertCount(4, $matches);

        foreach ($matches as $m) {
            $this->storeResult($admin, $t, $m, ['home_score' => 2, 'away_score' => 0]);
        }

        $semis = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Semifinal')->first();
        $this->assertTrue(TournamentMatch::where('phase_id', $semis->id)->whereNotNull('home_team_id')->exists());

        $target = $matches->first()->fresh();
        $response = $this->destroyResult($admin, $t, $target);

        $response->assertRedirect();
        $this->assertNotNull(session('error'));
        $this->assertSame('finished', $target->fresh()->status);
        $this->assertNotNull($target->fresh()->winner_team_id);
    }

    public function test_se_puede_anular_cuartos_si_semifinal_todavia_no_tiene_equipos(): void
    {
        $admin = $this->admin();
        $t = $this->makeKnockoutOf($admin, 8);

        $cuartos = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Cuartos de Final')->first();
        $matches = TournamentMatch::where('phase_id', $cuartos->id)->orderBy('match_number')->get();

        // Solo se completan 3 de los 4 partidos: la semifinal aún no se puebla.
        foreach ($matches->take(3) as $m) {
            $this->storeResult($admin, $t, $m, ['home_score' => 2, 'away_score' => 0]);
        }

        $semis = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Semifinal')->first();
        $this->assertFalse(TournamentMatch::where('phase_id', $semis->id)->whereNotNull('home_team_id')->exists());

        $target = $matches->first()->fresh();
        $response = $this->destroyResult($admin, $t, $target);

        $response->assertRedirect();
        $this->assertNotNull(session('status'));
        $this->assertSame('scheduled', $target->fresh()->status);
        $this->assertNull($target->fresh()->winner_team_id);
    }

    public function test_no_se_puede_anular_semifinal_si_final_o_tercer_puesto_ya_tienen_equipo(): void
    {
        $admin = $this->admin();
        $t = $this->makeKnockout($admin);

        $semis = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Semifinal')->first();
        $semiMatches = TournamentMatch::where('phase_id', $semis->id)->orderBy('match_number')->get();
        $this->storeResult($admin, $t, $semiMatches[0], ['home_score' => 2, 'away_score' => 0]);
        $this->storeResult($admin, $t, $semiMatches[1], ['home_score' => 3, 'away_score' => 1]);

        $final = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Final')->first();
        $this->assertTrue(TournamentMatch::where('phase_id', $final->id)->whereNotNull('home_team_id')->exists());

        $target = $semiMatches[0]->fresh();
        $response = $this->destroyResult($admin, $t, $target);

        $response->assertRedirect();
        $this->assertNotNull(session('error'));
        $this->assertSame('finished', $target->fresh()->status);
    }

    public function test_se_puede_anular_la_final_siempre_al_no_tener_ronda_posterior(): void
    {
        $admin = $this->admin();
        $t = $this->makeKnockout($admin);

        $semis = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Semifinal')->first();
        $semiMatches = TournamentMatch::where('phase_id', $semis->id)->orderBy('match_number')->get();
        $this->storeResult($admin, $t, $semiMatches[0], ['home_score' => 2, 'away_score' => 0]);
        $this->storeResult($admin, $t, $semiMatches[1], ['home_score' => 3, 'away_score' => 1]);

        $final = TournamentPhase::where('tournament_id', $t->id)->where('name', 'Final')->first();
        $finalMatch = TournamentMatch::where('phase_id', $final->id)->first();
        $this->storeResult($admin, $t, $finalMatch, ['home_score' => 2, 'away_score' => 1]);

        $response = $this->destroyResult($admin, $t, $finalMatch->fresh());

        $response->assertRedirect();
        $this->assertNotNull(session('status'));
        $this->assertSame('scheduled', $finalMatch->fresh()->status);
    }
}
