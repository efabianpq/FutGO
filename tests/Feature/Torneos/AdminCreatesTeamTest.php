<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * H7: el ADMIN del torneo crea equipos directamente (para equipos que no usan
 * la app). El club queda "por_validar" hasta asignarle un capitán (usuario del
 * sistema), momento en que pasa a "validado".
 */
class AdminCreatesTeamTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
    }

    private function tournament(User $admin, string $status = 'open'): Tournament
    {
        $t = Tournament::create([
            'name' => 'Copa Admin ' . uniqid(), 'slug' => 'copa-admin-' . uniqid(),
            'sport' => 'futbol', 'status' => $status, 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 8, 'classifies_per_group' => 1,
            'max_teams' => 8, 'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        $t->tournamentAdmins()->create(['user_id' => $admin->id]);

        return $t;
    }

    public function test_admin_crea_equipo_sin_capitan_queda_por_validar(): void
    {
        $admin = $this->admin();
        $t = $this->tournament($admin);

        $this->actingAs($admin)
            ->post(route('admin.torneos.equipos.create', $t), [
                'name'  => 'Equipo Sin Cuenta',
                'color' => 'Rojo',
            ])
            ->assertRedirect();

        // Club permanente creado en estado por_validar y sin capitán.
        $this->assertDatabaseHas('clubs', [
            'name'            => 'Equipo Sin Cuenta',
            'status'          => 'por_validar',
            'captain_user_id' => null,
        ]);

        // Inscrito en el torneo (team pending).
        $this->assertDatabaseHas('teams', [
            'tournament_id' => $t->id,
            'name'          => 'Equipo Sin Cuenta',
            'status'        => 'pending',
        ]);
    }

    public function test_admin_crea_equipo_con_capitan_queda_validado(): void
    {
        $admin   = $this->admin();
        $t       = $this->tournament($admin);
        $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos', 'email' => 'cap@futgo.test']);

        $this->actingAs($admin)
            ->post(route('admin.torneos.equipos.create', $t), [
                'name'          => 'Equipo Con Capitán',
                'captain_email' => 'cap@futgo.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('clubs', [
            'name'            => 'Equipo Con Capitán',
            'status'          => 'validado',
            'captain_user_id' => $captain->id,
        ]);

        // El capitán quedó en la plantilla permanente como capitán.
        $club = Club::where('name', 'Equipo Con Capitán')->first();
        $this->assertDatabaseHas('club_players', [
            'club_id'    => $club->id,
            'user_id'    => $captain->id,
            'is_captain' => true,
        ]);
    }

    public function test_admin_crea_equipo_con_email_inexistente_falla(): void
    {
        $admin = $this->admin();
        $t = $this->tournament($admin);

        $this->actingAs($admin)
            ->post(route('admin.torneos.equipos.create', $t), [
                'name'          => 'Equipo Fantasma',
                'captain_email' => 'noexiste@futgo.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('clubs', ['name' => 'Equipo Fantasma']);
    }

    public function test_asignar_capitan_valida_el_equipo_por_validar(): void
    {
        $admin   = $this->admin();
        $t       = $this->tournament($admin);
        $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos', 'email' => 'nuevo.cap@futgo.test']);

        // Admin crea el equipo sin capitán.
        $this->actingAs($admin)->post(route('admin.torneos.equipos.create', $t), ['name' => 'Equipo X']);

        $team = Team::where('tournament_id', $t->id)->where('name', 'Equipo X')->first();
        $this->assertTrue($team->club->isPorValidar());

        // Admin asigna capitán → el club queda validado.
        $this->actingAs($admin)
            ->patch(route('admin.torneos.equipos.assignCaptain', [$t, $team]), [
                'captain_email' => 'nuevo.cap@futgo.test',
            ])
            ->assertRedirect();

        $club = $team->club->fresh();
        $this->assertTrue($club->isValidado());
        $this->assertEquals($captain->id, $club->captain_user_id);
        $this->assertEquals($captain->id, $team->fresh()->captain_user_id);
    }

    public function test_club_por_validar_se_borra_al_eliminar_torneo_pero_validado_se_conserva(): void
    {
        $admin   = $this->admin();
        $t       = $this->tournament($admin);
        $captain = User::factory()->create(['is_active' => true, 'modules' => 'torneos', 'email' => 'val@futgo.test']);

        // Un equipo por_validar (creado por admin) y uno validado (con capitán).
        $this->actingAs($admin)->post(route('admin.torneos.equipos.create', $t), ['name' => 'Equipo Por Validar']);
        $this->actingAs($admin)->post(route('admin.torneos.equipos.create', $t), [
            'name' => 'Equipo Validado', 'captain_email' => 'val@futgo.test',
        ]);

        $porValidar = Club::where('name', 'Equipo Por Validar')->first();
        $validado   = Club::where('name', 'Equipo Validado')->first();

        // Eliminar el torneo (en open).
        $this->actingAs($admin)->delete(route('admin.torneos.destroy', $t))->assertRedirect();

        // El por_validar (huérfano) se borra; el validado se conserva.
        $this->assertDatabaseMissing('clubs', ['id' => $porValidar->id]);
        $this->assertDatabaseHas('clubs', ['id' => $validado->id]);
    }

    public function test_no_admin_no_puede_crear_equipos(): void
    {
        $admin  = $this->admin();
        $t      = $this->tournament($admin);
        $player = User::factory()->create(['is_active' => true, 'role' => 'user', 'modules' => 'torneos']);

        // El middleware ensure.torneo_admin redirige (no aborta con 403).
        $this->actingAs($player)
            ->post(route('admin.torneos.equipos.create', $t), ['name' => 'Equipo Intruso'])
            ->assertRedirect();

        $this->assertDatabaseMissing('clubs', ['name' => 'Equipo Intruso']);
    }
}
