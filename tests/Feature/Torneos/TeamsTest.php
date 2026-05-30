<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamsTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function torneoAdmin(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'torneo_admin',
            'modules'   => 'torneos',
        ]);
    }

    private function torneoUser(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ]);
    }

    private function makeTournament(User $admin, string $status = 'open'): Tournament
    {
        $t = Tournament::create([
            'name'               => 'Copa ' . uniqid(),
            'slug'               => 'copa-' . uniqid(),
            'sport'              => 'futbol',
            'status'             => $status,
            'format'             => 'groups_and_knockout',
            'groups_count'       => 2,
            'teams_per_group'    => 4,
            'classifies_per_group' => 2,
            'created_by_user_id' => $admin->id,
        ]);
        $t->admins()->attach($admin->id);
        return $t;
    }

    private function makeTeam(Tournament $tournament, User $captain, string $status = 'pending'): Team
    {
        $team = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $captain->id,
            'name'            => 'Equipo ' . uniqid(),
            'status'          => $status,
        ]);
        TeamPlayer::create([
            'team_id' => $team->id,
            'user_id' => $captain->id,
            'status'  => 'active',
        ]);
        return $team;
    }

    // ─── Tests: inscripción ─────────────────────────────────────────────────

    public function test_capitan_puede_inscribir_equipo_en_torneo_abierto(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');

        $this->actingAs($captain)
             ->post(route('torneos.equipo.store', $torneo), [
                 'name' => 'Los Cracks',
             ])
             ->assertRedirect(route('torneos.equipo.show', $torneo));

        $this->assertDatabaseHas('teams', [
            'tournament_id'   => $torneo->id,
            'name'            => 'Los Cracks',
            'captain_user_id' => $captain->id,
            'status'          => 'pending',
        ]);
    }

    public function test_no_se_puede_inscribir_equipo_en_torneo_no_abierto(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();

        foreach (['draft', 'in_progress', 'finished'] as $status) {
            $torneo = $this->makeTournament($admin, $status);

            $this->actingAs($captain)
                 ->post(route('torneos.equipo.store', $torneo), ['name' => 'Equipo X'])
                 ->assertRedirect(route('torneos.index'));
        }

        $this->assertDatabaseMissing('teams', ['name' => 'Equipo X']);
    }

    public function test_capitan_queda_registrado_como_jugador_automaticamente(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');

        $this->actingAs($captain)
             ->post(route('torneos.equipo.store', $torneo), ['name' => 'Team Auto']);

        $team = Team::where('name', 'Team Auto')->firstOrFail();

        $this->assertDatabaseHas('team_players', [
            'team_id' => $team->id,
            'user_id' => $captain->id,
            'status'  => 'active',
        ]);
    }

    public function test_no_se_puede_tener_dos_equipos_en_el_mismo_torneo(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');

        // Primera inscripción exitosa
        $this->actingAs($captain)
             ->post(route('torneos.equipo.store', $torneo), ['name' => 'Primer Equipo']);

        // Segunda inscripción debe fallar
        $this->actingAs($captain)
             ->post(route('torneos.equipo.store', $torneo), ['name' => 'Segundo Equipo'])
             ->assertRedirect();

        $this->assertDatabaseMissing('teams', ['name' => 'Segundo Equipo']);
    }

    public function test_usuario_sin_modulo_torneos_no_puede_inscribir(): void
    {
        $admin   = $this->torneoAdmin();
        $torneo  = $this->makeTournament($admin, 'open');

        $outsider = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'polla',
        ]);

        $this->actingAs($outsider)
             ->post(route('torneos.equipo.store', $torneo), ['name' => 'Intruso'])
             ->assertRedirect();

        $this->assertDatabaseMissing('teams', ['name' => 'Intruso']);
    }

    // ─── Tests: gestión de jugadores ────────────────────────────────────────

    public function test_capitan_puede_agregar_jugador_por_email(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $team    = $this->makeTeam($torneo, $captain);

        $player = $this->torneoUser();

        $this->actingAs($captain)
             ->post(route('torneos.equipo.players.add', $torneo), [
                 'email' => $player->email,
             ])
             ->assertRedirect();

        $this->assertDatabaseHas('team_players', [
            'team_id' => $team->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_no_se_puede_agregar_jugador_que_no_existe(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $this->makeTeam($torneo, $captain);

        $this->actingAs($captain)
             ->post(route('torneos.equipo.players.add', $torneo), [
                 'email' => 'noexiste@example.com',
             ])
             ->assertSessionHasErrors('email');
    }

    public function test_no_se_puede_agregar_jugador_que_ya_esta_en_otro_equipo(): void
    {
        $admin    = $this->torneoAdmin();
        $torneo   = $this->makeTournament($admin, 'open');

        $cap1     = $this->torneoUser();
        $cap2     = $this->torneoUser();
        $jugador  = $this->torneoUser();

        $team1 = $this->makeTeam($torneo, $cap1);
        $team2 = $this->makeTeam($torneo, $cap2);

        // Agregar jugador al equipo 1
        TeamPlayer::create([
            'team_id' => $team1->id,
            'user_id' => $jugador->id,
            'status'  => 'active',
        ]);

        // Intentar agregar el mismo jugador al equipo 2
        $this->actingAs($cap2)
             ->post(route('torneos.equipo.players.add', $torneo), [
                 'email' => $jugador->email,
             ])
             ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('team_players', [
            'team_id' => $team2->id,
            'user_id' => $jugador->id,
        ]);
    }

    // ─── Tests: admin aprueba/rechaza ────────────────────────────────────────

    public function test_torneo_admin_puede_aprobar_equipo(): void
    {
        $admin  = $this->torneoAdmin();
        $cap    = $this->torneoUser();
        $torneo = $this->makeTournament($admin, 'open');
        $team   = $this->makeTeam($torneo, $cap, 'pending');

        $this->actingAs($admin)
             ->patch(route('admin.torneos.equipos.approve', [$torneo, $team]))
             ->assertRedirect();

        $this->assertEquals('approved', $team->fresh()->status);
    }

    public function test_torneo_admin_puede_rechazar_equipo(): void
    {
        $admin  = $this->torneoAdmin();
        $cap    = $this->torneoUser();
        $torneo = $this->makeTournament($admin, 'open');
        $team   = $this->makeTeam($torneo, $cap, 'pending');

        $this->actingAs($admin)
             ->patch(route('admin.torneos.equipos.reject', [$torneo, $team]))
             ->assertRedirect();

        $this->assertEquals('rejected', $team->fresh()->status);
    }

    public function test_admin_ve_listado_equipos_del_torneo(): void
    {
        $admin  = $this->torneoAdmin();
        $cap    = $this->torneoUser();
        $torneo = $this->makeTournament($admin, 'open');
        $team   = $this->makeTeam($torneo, $cap);

        $this->actingAs($admin)
             ->get(route('admin.torneos.equipos.index', $torneo))
             ->assertOk()
             ->assertSee($team->name);
    }

    public function test_admin_ajeno_no_puede_aprobar_equipo(): void
    {
        $admin  = $this->torneoAdmin();
        $otro   = $this->torneoAdmin();
        $cap    = $this->torneoUser();
        $torneo = $this->makeTournament($admin, 'open');
        $team   = $this->makeTeam($torneo, $cap, 'pending');

        $this->actingAs($otro)
             ->patch(route('admin.torneos.equipos.approve', [$torneo, $team]))
             ->assertForbidden();
    }

    public function test_capitan_puede_quitar_jugador_con_confirmacion(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $player  = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $team    = $this->makeTeam($torneo, $captain);

        $tp = TeamPlayer::create([
            'team_id' => $team->id,
            'user_id' => $player->id,
            'status'  => 'active',
        ]);

        $this->actingAs($captain)
             ->delete(route('torneos.equipo.players.remove', [$torneo, $tp]))
             ->assertRedirect();

        $this->assertDatabaseMissing('team_players', ['id' => $tp->id]);
    }
}
