<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inscripción a torneos = enrolar un equipo permanente; aprobación del equipo
 * por el administrador del torneo.
 */
class TeamsTest extends TestCase
{
    use RefreshDatabase;

    private function torneoAdmin(): User
    {
        return User::factory()->create(['role' => 'user',]);
    }

    private function torneoUser(): User
    {
        return User::factory()->create(['role' => 'user',]);
    }

    private function makeTournament(User $admin, string $status = 'open'): Tournament
    {
        $t = Tournament::create([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => $status,
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            'created_by_user_id'   => $admin->id,
        ]);
        $t->admins()->attach($admin->id);
        return $t;
    }

    /** Equipo permanente con su capitán como jugador. */
    private function makeClub(User $captain, string $name = null): Club
    {
        $name ??= 'Equipo ' . uniqid();
        $club = Club::create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name) . '-' . uniqid(),
            'created_by_user_id' => $captain->id, 'captain_user_id' => $captain->id,
        ]);
        ClubPlayer::create(['club_id' => $club->id, 'user_id' => $captain->id, 'is_captain' => true, 'status' => 'active']);
        return $club;
    }

    /** Enrola un equipo permanente en un torneo (helper directo). */
    private function enroll(Club $club, Tournament $t): Team
    {
        return app(\App\Services\Torneos\ClubMembershipService::class)->enroll($club, $t);
    }

    // ─── Inscripción (enrolamiento) ───────────────────────────────────────────

    public function test_capitan_puede_enrolar_equipo_en_torneo_abierto(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $club    = $this->makeClub($captain, 'Los Cracks');

        $this->actingAs($captain)
            ->post(route('torneos.equipo.store', $torneo), ['club_id' => $club->id])
            ->assertRedirect(route('torneos.equipo.show', $torneo));

        $this->assertDatabaseHas('teams', [
            'tournament_id'   => $torneo->id,
            'club_id'         => $club->id,
            'captain_user_id' => $captain->id,
            'status'          => 'pending',
        ]);
    }

    public function test_no_se_puede_enrolar_en_torneo_no_abierto(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $club    = $this->makeClub($captain);

        foreach (['draft', 'in_progress', 'finished'] as $status) {
            $torneo = $this->makeTournament($admin, $status);
            $this->actingAs($captain)
                ->post(route('torneos.equipo.store', $torneo), ['club_id' => $club->id])
                ->assertRedirect(route('torneos.index'));

            $this->assertDatabaseMissing('teams', ['tournament_id' => $torneo->id, 'club_id' => $club->id]);
        }
    }

    public function test_enrolar_copia_la_plantilla_permanente(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $club    = $this->makeClub($captain);
        ClubPlayer::create(['club_id' => $club->id, 'user_id' => $this->torneoUser()->id, 'status' => 'active']);

        $this->actingAs($captain)->post(route('torneos.equipo.store', $torneo), ['club_id' => $club->id]);

        $team = Team::where('tournament_id', $torneo->id)->where('club_id', $club->id)->firstOrFail();
        $this->assertEquals(2, TeamPlayer::where('team_id', $team->id)->count());
    }

    public function test_no_dos_equipos_del_mismo_capitan_en_el_torneo(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $clubA   = $this->makeClub($captain, 'Equipo A');
        $clubB   = $this->makeClub($captain, 'Equipo B');

        $this->actingAs($captain)->post(route('torneos.equipo.store', $torneo), ['club_id' => $clubA->id])->assertRedirect();
        $this->actingAs($captain)->post(route('torneos.equipo.store', $torneo), ['club_id' => $clubB->id])->assertRedirect();

        $this->assertDatabaseMissing('teams', ['tournament_id' => $torneo->id, 'club_id' => $clubB->id]);
    }

    public function test_usuario_que_no_es_capitan_del_club_no_puede_enrolar(): void
    {
        $admin   = $this->torneoAdmin();
        $torneo  = $this->makeTournament($admin, 'open');
        $captain = $this->torneoUser();
        $club    = $this->makeClub($captain);

        $outsider = User::factory()->create(['role' => 'user']);

        $this->actingAs($outsider)
            ->post(route('torneos.equipo.store', $torneo), ['club_id' => $club->id])
            ->assertForbidden();

        $this->assertDatabaseMissing('teams', ['tournament_id' => $torneo->id, 'club_id' => $club->id]);
    }

    // ─── Aprobación del equipo por el admin del torneo ────────────────────────

    public function test_torneo_admin_puede_aprobar_equipo(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $club    = $this->makeClub($captain);
        $team    = $this->enroll($club, $torneo);

        $this->actingAs($admin)
            ->patch(route('admin.torneos.equipos.approve', [$torneo, $team]))
            ->assertRedirect();

        $this->assertEquals('approved', $team->fresh()->status);
    }

    public function test_torneo_admin_puede_rechazar_equipo(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $club    = $this->makeClub($captain);
        $team    = $this->enroll($club, $torneo);

        $this->actingAs($admin)
            ->patch(route('admin.torneos.equipos.reject', [$torneo, $team]))
            ->assertRedirect();

        $this->assertEquals('rejected', $team->fresh()->status);
    }

    public function test_admin_ve_listado_equipos_del_torneo(): void
    {
        $admin   = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $club    = $this->makeClub($captain, 'Equipo Visible');
        $this->enroll($club, $torneo);

        $this->actingAs($admin)
            ->get(route('admin.torneos.equipos.index', $torneo))
            ->assertOk()
            ->assertSee('Equipo Visible');
    }

    public function test_admin_ajeno_no_puede_aprobar_equipo(): void
    {
        $admin   = $this->torneoAdmin();
        $otro    = $this->torneoAdmin();
        $captain = $this->torneoUser();
        $torneo  = $this->makeTournament($admin, 'open');
        $club    = $this->makeClub($captain);
        $team    = $this->enroll($club, $torneo);

        $this->actingAs($otro)
            ->patch(route('admin.torneos.equipos.approve', [$torneo, $team]))
            ->assertForbidden();
    }
}
