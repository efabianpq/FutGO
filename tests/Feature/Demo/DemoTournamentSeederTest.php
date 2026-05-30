<?php

namespace Tests\Feature\Demo;

use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use Database\Seeders\DemoTournamentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTournamentSeederTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        // El admin principal necesita existir para el seeder (no lo crea el seeder)
        User::factory()->create([
            'email'    => 'admin@soypachonmundial.com',
            'role'     => 'admin',
            'is_active' => true,
            'modules'  => 'full',
        ]);

        (new DemoTournamentSeeder())->run();
        $this->tournament = Tournament::where('name', 'Copa FutGO Demo 2026')->firstOrFail();
    }

    public function test_seeder_ejecuta_correctamente(): void
    {
        $this->assertNotNull($this->tournament);
        $this->assertEquals('in_progress', $this->tournament->status);
        $this->assertEquals('groups_and_knockout', $this->tournament->format);
    }

    public function test_usuarios_demo_creados(): void
    {
        $this->assertDatabaseHas('users', ['email' => 'admin.torneo@demo.futgo.com', 'role' => 'torneo_admin']);
        $this->assertDatabaseHas('users', ['email' => 'ldn.capitan@demo.futgo.com']);
        $this->assertDatabaseHas('users', ['email' => 'tig.capitan@demo.futgo.com']);
        $this->assertDatabaseHas('users', ['email' => 'ldn.j1@demo.futgo.com']);
    }

    public function test_equipos_creados_correctamente(): void
    {
        $teams = $this->tournament->teams()->where('status', 'approved')->get();
        $this->assertCount(8, $teams);

        // Verificar equipos esperados
        $names = $teams->pluck('name');
        $this->assertTrue($names->contains('Leones del Norte'));
        $this->assertTrue($names->contains('Tigres FC'));
        $this->assertTrue($names->contains('Delfines Azules'));
    }

    public function test_jugadores_creados_por_equipo(): void
    {
        $teams = $this->tournament->teams()->where('status', 'approved')->get();

        foreach ($teams as $team) {
            $playerCount = TeamPlayer::where('team_id', $team->id)
                ->where('status', 'active')
                ->count();
            $this->assertGreaterThanOrEqual(11, $playerCount,
                "El equipo {$team->name} debería tener al menos 11 jugadores activos"
            );
        }
    }

    public function test_partidos_de_grupos_creados(): void
    {
        $groupPhase = $this->tournament->phases()->where('type', 'groups')->first();
        $this->assertNotNull($groupPhase);

        $matchCount = TournamentMatch::where('phase_id', $groupPhase->id)->count();
        $this->assertGreaterThanOrEqual(12, $matchCount,
            "Deben existir al menos 12 partidos de grupos (C(4,2)×2 grupos)"
        );
    }

    public function test_partidos_finalizados_tienen_resultado(): void
    {
        $phaseIds = $this->tournament->phases()->pluck('id');
        $finished = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->where('status', 'finished')
            ->get();

        $this->assertGreaterThanOrEqual(6, $finished->count(),
            "Deben existir al menos 6 partidos finalizados"
        );

        foreach ($finished as $match) {
            $this->assertNotNull($match->home_score, "home_score no debe ser null en partido finalizado");
            $this->assertNotNull($match->away_score, "away_score no debe ser null en partido finalizado");
        }
    }

    public function test_standings_generados(): void
    {
        $groupPhase = $this->tournament->phases()->where('type', 'groups')->first();
        $standingCount = Standing::where('phase_id', $groupPhase->id)->count();

        $this->assertGreaterThan(0, $standingCount,
            "Deben existir standings calculados para la fase de grupos"
        );
    }

    public function test_estadisticas_de_jugadores_generadas(): void
    {
        $statsCount = PlayerStat::where('tournament_id', $this->tournament->id)
            ->where('matches_played', '>', 0)
            ->count();

        $this->assertGreaterThan(0, $statsCount,
            "Deben existir estadísticas de jugadores con al menos 1 partido jugado"
        );
    }

    public function test_eliminatorias_generadas(): void
    {
        $knockoutPhase = $this->tournament->phases()
            ->where('type', 'knockout')
            ->orderBy('order')
            ->first();

        $this->assertNotNull($knockoutPhase,
            "Debe existir al menos una fase de eliminatoria"
        );

        $knockoutMatches = TournamentMatch::where('phase_id', $knockoutPhase->id)->count();
        $this->assertGreaterThan(0, $knockoutMatches,
            "La fase de eliminatoria debe tener partidos"
        );
    }

    public function test_fase_grupos_tiene_dos_grupos(): void
    {
        $groupPhase = $this->tournament->phases()->where('type', 'groups')->first();
        $groupCount = $groupPhase->groups()->count();
        $this->assertEquals(2, $groupCount, "Deben existir exactamente 2 grupos");
    }

    public function test_capitan_puede_acceder_al_hub(): void
    {
        $captain = User::where('email', 'ldn.capitan@demo.futgo.com')->first();

        $this->actingAs($captain)
            ->get(route('torneos.hub', $this->tournament))
            ->assertOk()
            ->assertSee('Leones del Norte');
    }

    public function test_admin_torneo_puede_acceder_al_dashboard_admin(): void
    {
        $torneoAdmin = User::where('email', 'admin.torneo@demo.futgo.com')->first();

        $this->actingAs($torneoAdmin)
            ->get(route('admin.torneos.show', $this->tournament))
            ->assertOk()
            ->assertSee('Copa FutGO Demo 2026');
    }

    public function test_partidos_futuros_tienen_fecha_programada(): void
    {
        $phaseIds = $this->tournament->phases()->pluck('id');
        $futureMatches = TournamentMatch::whereIn('phase_id', $phaseIds)
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->get();

        // Al menos algunos partidos futuros deben tener fecha
        $this->assertGreaterThan(0, $futureMatches->count(),
            "Deben existir partidos programados con fecha definida"
        );

        foreach ($futureMatches as $m) {
            $this->assertTrue($m->scheduled_at->isFuture(),
                "El partido #{$m->match_number} debe tener fecha futura"
            );
        }
    }

    public function test_hay_goleadores_en_la_demo(): void
    {
        $topScorer = PlayerStat::where('tournament_id', $this->tournament->id)
            ->where('goals', '>', 0)
            ->orderByDesc('goals')
            ->first();

        $this->assertNotNull($topScorer,
            "Debe existir al menos un jugador con goles registrados"
        );
        $this->assertGreaterThan(0, $topScorer->goals);
    }

    public function test_seeder_es_idempotente(): void
    {
        // Ejecutar el seeder una segunda vez no debe generar duplicados
        (new DemoTournamentSeeder())->run();

        $tournamentCount = Tournament::where('name', 'Copa FutGO Demo 2026')->count();
        $this->assertEquals(1, $tournamentCount,
            "Solo debe existir un torneo demo después de ejecutar el seeder dos veces"
        );
    }
}
