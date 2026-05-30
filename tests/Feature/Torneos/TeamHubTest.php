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

    // ─── Gestión de plantilla ────────────────────────────────────────────────

    public function test_capitan_no_puede_gestionar_otro_equipo(): void
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);
        $t     = $this->makeTournament($admin);

        $capA = $this->makeUser();
        $capB = $this->makeUser();
        $teamA = $this->makeTeam($t, $capA);
        $teamB = $this->makeTeam($t, $capB);

        // Jugador pendiente en el equipo B
        $pendingB = TeamPlayer::create([
            'team_id' => $teamB->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        // El capitán A intenta aprobar al pendiente de B → 403 (ensure.team_member)
        $this->actingAs($capA)
            ->post(route('torneos.equipo.players.approve', [$t, $pendingB]))
            ->assertForbidden();

        $this->assertEquals('pending', $pendingB->fresh()->status);
    }

    public function test_aprobar_jugador_funciona(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        $this->actingAs($captain)
            ->post(route('torneos.equipo.players.approve', [$t, $pending]))
            ->assertRedirect();

        $this->assertEquals('active', $pending->fresh()->status);
    }

    public function test_rechazar_jugador_funciona(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        $this->actingAs($captain)
            ->post(route('torneos.equipo.players.reject', [$t, $pending]))
            ->assertRedirect();

        $this->assertEquals('rejected', $pending->fresh()->status);
    }

    public function test_jugador_raso_no_puede_aprobar(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $player  = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);
        TeamPlayer::create(['team_id' => $team->id, 'user_id' => $player->id, 'status' => 'active']);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        // El jugador raso es miembro (pasa ensure.team_member) pero no es capitán → 403 en el controlador
        $this->actingAs($player)
            ->post(route('torneos.equipo.players.approve', [$t, $pending]))
            ->assertForbidden();

        $this->assertEquals('pending', $pending->fresh()->status);
    }

    public function test_torneo_admin_puede_aprobar_jugador(): void
    {
        $admin   = $this->makeUser(['role' => 'torneo_admin']);
        $captain = $this->makeUser();
        $t       = $this->makeTournament($admin);
        $team    = $this->makeTeam($t, $captain);

        $pending = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $this->makeUser()->id, 'status' => 'pending',
        ]);

        $this->actingAs($admin)
            ->post(route('torneos.equipo.players.approve', [$t, $pending]))
            ->assertRedirect();

        $this->assertEquals('active', $pending->fresh()->status);
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
            ->assertSee('Plantilla')
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

    public function test_solicitudes_pendientes_visibles_para_capitan(): void
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
            ->assertSee('Solicitudes pendientes')
            ->assertSee('Solicitante Pepe');
    }
}
