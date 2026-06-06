<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamHubTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            ['is_active' => true, 'modules' => 'torneos'],
            $attrs
        ));
    }

    private function makeTournament(User $admin, string $status = 'open'): Tournament
    {
        $t = Tournament::create([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => $status,
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
            'category'             => 'libre',
            'visibility'           => 'public',
            'created_by_user_id'   => $admin->id,
        ]);
        $t->tournamentAdmins()->create(['user_id' => $admin->id]);
        return $t;
    }

    private function makeTeam(Tournament $t, User $captain, string $status = 'approved'): Team
    {
        $team = Team::create([
            'tournament_id'   => $t->id,
            'captain_user_id' => $captain->id,
            'name'            => 'Equipo ' . uniqid(),
            'status'          => $status,
        ]);
        TeamPlayer::create(['team_id' => $team->id, 'user_id' => $captain->id, 'status' => 'active']);
        return $team;
    }

    // ─── Acceso ────────────────────────────────────────────────────────────────

    public function test_capitan_puede_acceder(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $this->actingAs($captain)
            ->get(route('torneos.equipo.show', $t))
            ->assertOk()
            ->assertSee($team->name);
    }

    public function test_jugador_puede_acceder(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $player  = $this->makeUser(['name' => 'Jugador Raso']);
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);
        TeamPlayer::create(['team_id' => $team->id, 'user_id' => $player->id, 'status' => 'active']);

        $this->actingAs($player)
            ->get(route('torneos.equipo.show', $t))
            ->assertOk()
            ->assertSee($team->name);
    }

    public function test_usuario_externo_no_puede_acceder(): void
    {
        $admin    = $this->makeUser(['role' => 'torneo_admin']);
        $captain  = $this->makeUser();
        $outsider = $this->makeUser(['name' => 'Ajeno']);
        $t        = $this->makeTournament($admin);
        $this->makeTeam($t, $captain);

        // Sin equipo en este torneo → redirige a inscribir (no ve datos de equipo)
        $this->actingAs($outsider)
            ->get(route('torneos.equipo.show', $t))
            ->assertRedirect(route('torneos.equipo.inscribir', $t));
    }

    // ─── Aprobación de jugadores tardíos (la hace el ADMIN del torneo) ─────────

    public function test_admin_del_torneo_aprueba_jugador_pendiente(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.torneos.equipos.players.approve', [$t, $team, $pending]))
            ->assertRedirect();

        $this->assertEquals('active', $pending->fresh()->status);
    }

    public function test_admin_del_torneo_rechaza_jugador_pendiente(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.torneos.equipos.players.reject', [$t, $team, $pending]))
            ->assertRedirect();

        $this->assertEquals('rejected', $pending->fresh()->status);
    }

    public function test_admin_ajeno_no_puede_aprobar_jugador(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $otro    = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        $this->actingAs($otro)
            ->patch(route('admin.torneos.equipos.players.approve', [$t, $team, $pending]))
            ->assertForbidden();

        $this->assertEquals('pending', $pending->fresh()->status);
    }

    // ─── Dashboard ───────────────────────────────────────────────────────────

    public function test_dashboard_carga_correctamente(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $this->actingAs($captain)
            ->get(route('torneos.equipo.show', $t))
            ->assertOk()
            ->assertSee('Plantilla en este torneo')
            ->assertSee('Próximos partidos')
            ->assertSee('PJ');
    }

    public function test_perfil_de_jugador_accesible_desde_plantilla(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);
        $captainPlayer = TeamPlayer::where('team_id', $team->id)->first();

        // El enlace al perfil del jugador (torneos.estadisticas.jugador) responde
        $this->actingAs($captain)
            ->get(route('torneos.estadisticas.jugador', [$t, $captainPlayer]))
            ->assertOk();
    }

    public function test_hub_muestra_estadisticas_acotadas_al_torneo_h7(): void
    {
        // H7: dentro de un torneo, "Mi equipo" muestra el contexto y las stats de
        // ESE torneo (la consolidación cross-torneo vive en el perfil del club).
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $this->actingAs($captain)
            ->get(route('torneos.equipo.show', $t))
            ->assertOk()
            ->assertSee('Plantilla en este torneo')   // scope explícito del torneo
            ->assertSee($t->name);                    // contexto del torneo actual
    }

    public function test_aviso_de_pendientes_visible_en_el_hub(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pendingUser = $this->makeUser(['name' => 'Solicitante Pepe']);
        TeamPlayer::create(['team_id' => $team->id, 'user_id' => $pendingUser->id, 'status' => 'pending']);

        $this->actingAs($captain)
            ->get(route('torneos.equipo.show', $t))
            ->assertOk()
            ->assertSee('pendiente(s) de aprobación del organizador');
    }
}
