<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptainDashboardTest extends TestCase
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
     * Torneo abierto con un equipo aprobado cuyo capitán es $captain.
     *
     * @return array{0:Tournament,1:Team,2:User}
     */
    private function makeScenario(): array
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);

        $tournament = Tournament::create([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => 4,
            'classifies_per_group' => 1,
            'max_teams'            => 4,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => 90,
            'min_players_per_team' => 5,
            'max_players_per_team' => 12,
            'category'             => 'libre',
            'visibility'           => 'public',
            'created_by_user_id'   => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $captain = $this->makeUser(['name' => 'Capitán Demo']);
        $team = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $captain->id,
            'name'            => 'Los Cracks',
            'status'          => 'approved',
        ]);
        TeamPlayer::create(['team_id' => $team->id, 'user_id' => $captain->id, 'status' => 'active']);

        return [$tournament, $team, $captain];
    }

    private function addPlayer(Team $team, string $status, string $name): TeamPlayer
    {
        $user = $this->makeUser(['name' => $name]);

        return TeamPlayer::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'status'  => $status,
        ]);
    }

    // ─── Acceso ────────────────────────────────────────────────────────────────

    public function test_capitan_accede_a_su_panel(): void
    {
        [$tournament, $team, $captain] = $this->makeScenario();

        $this->actingAs($captain)
            ->get(route('torneos.capitan'))
            ->assertOk()
            ->assertSee('Panel del Capitán')
            ->assertSee($team->name);
    }

    public function test_panel_muestra_plantilla_y_solicitudes(): void
    {
        [$tournament, $team, $captain] = $this->makeScenario();
        $this->addPlayer($team, 'pending', 'Solicitante Pendiente');

        $this->actingAs($captain)
            ->get(route('torneos.capitan'))
            ->assertOk()
            ->assertSee('Gestión de jugadores')
            ->assertSee('Pendientes')
            ->assertSee('Solicitante Pendiente');
    }

    public function test_panel_muestra_estadisticas_del_equipo(): void
    {
        [$tournament, $team, $captain] = $this->makeScenario();

        $this->actingAs($captain)
            ->get(route('torneos.capitan'))
            ->assertOk()
            ->assertSee('PJ')
            ->assertSee('GF');
    }

    public function test_no_capitan_recibe_403(): void
    {
        $this->makeScenario(); // existe un torneo, pero este usuario no capitanea nada

        $randomUser = $this->makeUser(['name' => 'Sin Equipo']);

        $this->actingAs($randomUser)
            ->get(route('torneos.capitan'))
            ->assertForbidden();
    }

    // ─── Acción de gestión desde el panel ───────────────────────────────────────

    public function test_capitan_puede_aprobar_jugador_desde_panel(): void
    {
        [$tournament, $team, $captain] = $this->makeScenario();
        $pending = $this->addPlayer($team, 'pending', 'Aspirante');

        $this->actingAs($captain)
            ->post(route('torneos.equipo.players.approve', [$tournament, $pending]))
            ->assertRedirect();

        $this->assertSame('active', $pending->fresh()->status);
    }

    // ─── Menú por rol ────────────────────────────────────────────────────────────

    public function test_navbar_capitan_muestra_panel(): void
    {
        [$tournament, $team, $captain] = $this->makeScenario();

        $this->actingAs($captain)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Panel Capitán');
    }
}
