<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TournamentAdminFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            ['is_active' => true, 'modules' => 'torneos'],
            $attrs
        ));
    }

    /**
     * Crea un torneo round_robin administrado por $admin con N equipos aprobados.
     * Si $withFixture, genera el fixture (deja el torneo in_progress).
     *
     * @return array{0:Tournament,1:\Illuminate\Support\Collection}
     */
    private function makeTournament(User $admin, int $n = 4, bool $withFixture = false): array
    {
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

        $teams = collect();
        for ($i = 0; $i < $n; $i++) {
            $cap  = $this->makeUser(['name' => "Capitan{$i}"]);
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $cap->id,
                'name'            => "Equipo{$i}",
                'status'          => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'status' => 'active']);
            $teams->push($team);
        }

        if ($withFixture) {
            app(FixtureGeneratorService::class)->generate($tournament);
            $tournament->refresh();
        }

        return [$tournament, $teams];
    }

    // ─── Mis Torneos ─────────────────────────────────────────────────────────

    public function test_torneo_admin_ve_solo_sus_torneos(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$mine] = $this->makeTournament($admin);

        $otherAdmin = $this->makeUser(['role' => 'torneo_admin']);
        [$theirs] = $this->makeTournament($otherAdmin);

        $this->actingAs($admin)
            ->get(route('torneos.index'))
            ->assertOk()
            ->assertSee($mine->name)
            ->assertDontSee($theirs->name);
    }

    public function test_mis_torneos_carga_informacion_real(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$tournament] = $this->makeTournament($admin, 4);

        $this->actingAs($admin)
            ->get(route('torneos.index'))
            ->assertOk()
            ->assertSee($tournament->name)
            ->assertSee('Inscripción')   // badge de estado open
            ->assertSee('Editar')        // acción de administrador (H3: Ver/Editar)
            ->assertDontSee('Próximamente');
    }

    // ─── Navegación operativa ──────────────────────────────────────────────────

    public function test_navegacion_a_fixture_funciona(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$tournament, $teams] = $this->makeTournament($admin, 4, withFixture: true);

        // Vista operativa de partidos (fixture + resultados).
        $this->actingAs($admin)
            ->get(route('admin.torneos.partidos.index', $tournament))
            ->assertOk()
            ->assertSee($teams[0]->name)
            ->assertSee('Programar');

        // Calendario público (cronograma).
        $this->actingAs($admin)
            ->get(route('torneos.cronograma.index', $tournament))
            ->assertOk();
    }

    public function test_navegacion_a_estadisticas_funciona(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$tournament] = $this->makeTournament($admin, 4, withFixture: true);

        $this->actingAs($admin)
            ->get(route('torneos.estadisticas.index', $tournament))
            ->assertOk();
    }

    public function test_dashboard_admin_enlaza_estadisticas_y_fixture(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$tournament] = $this->makeTournament($admin, 4, withFixture: true);

        $this->actingAs($admin)
            ->get(route('admin.torneos.show', $tournament))
            ->assertOk()
            ->assertSee(route('torneos.estadisticas.index', $tournament))
            ->assertSee(route('torneos.cronograma.index', $tournament))
            ->assertDontSee('Próximamente');
    }

    // ─── Programación de partidos ──────────────────────────────────────────────

    public function test_torneo_admin_puede_programar_partido(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$tournament] = $this->makeTournament($admin, 4, withFixture: true);

        $match = TournamentMatch::whereHas('phase', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->orderBy('match_number')->first();

        $this->actingAs($admin)
            ->get(route('admin.torneos.partidos.programar', [$tournament, $match]))
            ->assertOk()
            ->assertSee('Programación');

        $this->actingAs($admin)
            ->patch(route('admin.torneos.partidos.programar.update', [$tournament, $match]), [
                'scheduled_at' => '2026-06-15T18:30',
                'venue'        => 'Cancha Central',
                'status'       => 'postponed',
                'observations' => 'Reprogramado por lluvia.',
            ])
            ->assertRedirect(route('admin.torneos.partidos.index', $tournament));

        $match->refresh();
        $this->assertSame('postponed', $match->status);
        $this->assertSame('Cancha Central', $match->venue);
        $this->assertSame('Reprogramado por lluvia.', $match->observations);
        $this->assertNotNull($match->scheduled_at);
    }

    // ─── Autorización ──────────────────────────────────────────────────────────

    public function test_torneo_admin_ajeno_recibe_403(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$mine] = $this->makeTournament($admin);

        $otherAdmin = $this->makeUser(['role' => 'torneo_admin']);

        $this->actingAs($otherAdmin)
            ->get(route('admin.torneos.show', $mine))
            ->assertForbidden();
    }

    public function test_usuario_sin_rol_admin_no_entra_a_gestion(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        [$tournament] = $this->makeTournament($admin);

        $player = $this->makeUser(); // sin rol torneo_admin

        $this->actingAs($player)
            ->get(route('admin.torneos.index'))
            ->assertRedirect(route('predictions.index'));
    }

    // ─── Menú por rol ──────────────────────────────────────────────────────────

    public function test_navbar_muestra_gestion_para_torneo_admin(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        $this->makeTournament($admin);

        $this->actingAs($admin)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Gestión Torneos');
    }
}
