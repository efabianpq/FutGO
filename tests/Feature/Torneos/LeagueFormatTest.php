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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H6: formato "liga" — el admin arma el fixture (manual o auto round-robin) y
 * genera la eliminatoria desde la tabla de posiciones cuando los partidos terminan.
 */
class LeagueFormatTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'user',]);
    }

    private function makeLeague(User $admin, int $teamCount = 4, int $classifies = 2): array
    {
        $tournament = Tournament::create([
            'name' => 'Liga ' . uniqid(), 'slug' => 'liga-' . uniqid(),
            'sport' => 'futbol', 'status' => 'open', 'format' => 'liga',
            'groups_count' => 1, 'teams_per_group' => 1,
            'classifies_per_group' => $classifies, 'max_teams' => $teamCount,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = [];
        for ($i = 0; $i < $teamCount; $i++) {
            $cap = User::factory()->create([]);
            $team = Team::create([
                'tournament_id' => $tournament->id, 'captain_user_id' => $cap->id,
                'name' => "Equipo $i", 'status' => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'is_captain' => true, 'status' => 'active']);
            $teams[] = $team;
        }

        return [$tournament, $teams];
    }

    public function test_se_puede_crear_torneo_en_formato_liga(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.torneos.store'), [
            'name' => 'Mi Liga', 'sport' => 'futbol', 'format' => 'liga',
            'max_teams' => 10, 'classifies_per_group' => 4,
            'visibility' => 'public', 'category' => 'libre',
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'knockout_tiebreak' => 'penalties',
            'min_players_per_team' => 5, 'max_players_per_team' => 20,
            'match_duration' => 90, 'max_substitutions' => 5,
        ])->assertRedirect();

        $this->assertDatabaseHas('tournaments', [
            'name' => 'Mi Liga', 'format' => 'liga', 'max_teams' => 10, 'classifies_per_group' => 4,
        ]);
    }

    public function test_liga_con_clasificados_no_potencia_de_dos_es_invalida(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.torneos.store'), [
            'name' => 'Liga Mala', 'sport' => 'futbol', 'format' => 'liga',
            'max_teams' => 10, 'classifies_per_group' => 3,   // 3 no es potencia de 2
            'visibility' => 'public', 'category' => 'libre',
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'knockout_tiebreak' => 'penalties',
            'min_players_per_team' => 5, 'max_players_per_team' => 20,
            'match_duration' => 90, 'max_substitutions' => 5,
        ])->assertSessionHasErrors('classifies_per_group');
    }

    public function test_activar_liga_crea_fase_y_pasa_a_in_progress(): void
    {
        $admin = $this->admin();
        [$tournament] = $this->makeLeague($admin);

        $this->actingAs($admin)
            ->post(route('admin.torneos.liga.activate', $tournament))
            ->assertRedirect();

        $tournament->refresh();
        $this->assertEquals('in_progress', $tournament->status);

        // Fase liga (groups) con un grupo y todos los equipos, sin partidos aún.
        $phase = $tournament->phases()->where('type', 'groups')->first();
        $this->assertNotNull($phase);
        $this->assertEquals('Liga', $phase->name);
        $this->assertEquals(0, $phase->matches()->count());
        $this->assertEquals(4, $phase->groups()->first()->teams()->count());
    }

    public function test_admin_agrega_partido_manual_a_la_liga(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin);
        app(FixtureGeneratorService::class)->setupLeague($tournament);

        $this->actingAs($admin)
            ->post(route('admin.torneos.liga.matches.store', $tournament), [
                'home_team_id' => $teams[0]->id,
                'away_team_id' => $teams[1]->id,
                'venue'        => 'Cancha Central',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_matches', [
            'home_team_id' => $teams[0]->id,
            'away_team_id' => $teams[1]->id,
            'venue'        => 'Cancha Central',
            'status'       => 'scheduled',
        ]);
    }

    public function test_no_se_puede_agregar_partido_de_equipo_contra_si_mismo(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin);
        app(FixtureGeneratorService::class)->setupLeague($tournament);

        $this->actingAs($admin)
            ->post(route('admin.torneos.liga.matches.store', $tournament), [
                'home_team_id' => $teams[0]->id,
                'away_team_id' => $teams[0]->id,
            ])
            ->assertSessionHasErrors('home_team_id');
    }

    public function test_auto_generar_round_robin_crea_todos_los_partidos(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin, 4);
        app(FixtureGeneratorService::class)->setupLeague($tournament);

        $this->actingAs($admin)
            ->post(route('admin.torneos.liga.roundRobin', $tournament))
            ->assertRedirect();

        // 4 equipos → 4*3/2 = 6 partidos.
        $phase = $tournament->phases()->where('type', 'groups')->first();
        $this->assertEquals(6, $phase->matches()->count());
    }

    public function test_auto_round_robin_no_duplica_partidos_existentes(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin, 4);
        $fixture = app(FixtureGeneratorService::class);
        $fixture->setupLeague($tournament);

        // Cargar manualmente el cruce 0 vs 1.
        $fixture->addLeagueMatch($tournament, $teams[0]->id, $teams[1]->id);

        // Auto-generar el resto.
        $this->actingAs($admin)->post(route('admin.torneos.liga.roundRobin', $tournament));

        // Total sigue siendo 6 (no duplica el 0 vs 1).
        $phase = $tournament->phases()->where('type', 'groups')->first();
        $this->assertEquals(6, $phase->matches()->count());
    }

    public function test_standings_se_calculan_en_la_liga_al_cargar_resultados(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin, 4);
        $fixture = app(FixtureGeneratorService::class);
        $fixture->setupLeague($tournament);
        $fixture->generateLeagueRoundRobin($tournament);

        $phase = $tournament->phases()->where('type', 'groups')->first();
        $match = $phase->matches()->orderBy('match_number')->first();

        // Cargar un resultado: local gana 2-0.
        $this->actingAs($admin)->post(
            route('admin.torneos.partidos.store', [$tournament, $match]),
            ['home_score' => 2, 'away_score' => 0]
        );

        $standing = Standing::where('group_id', $phase->groups()->first()->id)
            ->where('team_id', $match->home_team_id)
            ->first();

        $this->assertNotNull($standing);
        $this->assertEquals(3, $standing->points);   // victoria = 3 pts
        $this->assertEquals(2, $standing->goals_for);
    }

    public function test_generar_eliminatoria_desde_la_tabla_con_top_2(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin, 4, classifies: 2);
        $fixture = app(FixtureGeneratorService::class);
        $fixture->setupLeague($tournament);
        $fixture->generateLeagueRoundRobin($tournament);

        $phase = $tournament->phases()->where('type', 'groups')->first();

        // Finalizar TODOS los partidos de la liga (resultados arbitrarios).
        foreach ($phase->matches()->orderBy('match_number')->get() as $m) {
            $this->actingAs($admin)->post(
                route('admin.torneos.partidos.store', [$tournament, $m]),
                ['home_score' => 1, 'away_score' => 0]
            );
        }

        // Generar la eliminatoria con los 2 mejores.
        $this->actingAs($admin)
            ->post(route('admin.torneos.liga.knockout', $tournament))
            ->assertRedirect();

        // Se creó una fase knockout (Final) con un partido poblado por los top-2.
        $knockout = $tournament->phases()->where('type', 'knockout')->first();
        $this->assertNotNull($knockout);
        $final = $knockout->matches()->first();
        $this->assertNotNull($final->home_team_id);
        $this->assertNotNull($final->away_team_id);

        // La fase de liga quedó cerrada.
        $this->assertEquals('completed', $phase->fresh()->status);
    }

    public function test_no_se_genera_eliminatoria_con_partidos_pendientes(): void
    {
        $admin = $this->admin();
        [$tournament, $teams] = $this->makeLeague($admin, 4, classifies: 2);
        $fixture = app(FixtureGeneratorService::class);
        $fixture->setupLeague($tournament);
        $fixture->generateLeagueRoundRobin($tournament);

        // Sin cargar resultados → partidos pendientes.
        $this->actingAs($admin)
            ->post(route('admin.torneos.liga.knockout', $tournament))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($tournament->phases()->where('type', 'knockout')->exists());
    }
}
