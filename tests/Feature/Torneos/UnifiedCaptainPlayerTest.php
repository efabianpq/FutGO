<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Equipo PERMANENTE y transversal: creación standalone, plantilla propia,
 * capitanía derivada del equipo y enrolamiento a torneos.
 */
class UnifiedCaptainPlayerTest extends TestCase
{
    use RefreshDatabase;

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

    /** Crea un equipo permanente vía HTTP (el creador queda capitán). */
    private function createClub(User $user, string $name): Club
    {
        $this->actingAs($user)->post(route('torneos.equipos.store'), ['name' => $name])->assertRedirect();
        return Club::where('captain_user_id', $user->id)->where('name', $name)->firstOrFail();
    }

    // ─── Creación / capitanía ─────────────────────────────────────────────────

    public function test_crear_equipo_convierte_al_creador_en_capitan(): void
    {
        $user = $this->torneoUser();
        $club = $this->createClub($user, 'Halcones');

        $this->assertEquals($user->id, $club->captain_user_id);
        $this->assertDatabaseHas('club_players', [
            'club_id' => $club->id, 'user_id' => $user->id, 'is_captain' => true,
        ]);
    }

    public function test_no_hay_interfaz_distinta_cualquiera_crea_equipo(): void
    {
        // Un usuario común (no "capitán" global) puede crear equipo y queda capitán.
        $user = $this->torneoUser();
        $this->assertFalse($user->isCaptainAnywhere());

        $this->createClub($user, 'Nuevo Equipo');

        $this->assertTrue($user->fresh()->isCaptainAnywhere());
    }

    // ─── Mis Equipos: dirijo + juego ──────────────────────────────────────────

    public function test_mis_equipos_muestra_los_que_dirijo_y_donde_juego(): void
    {
        $user = $this->torneoUser();
        $propio = $this->createClub($user, 'Equipo Propio');

        // Es jugador (no capitán) de otro equipo.
        $otro = $this->torneoUser();
        $ajeno = $this->createClub($otro, 'Equipo Ajeno');
        ClubPlayer::create(['club_id' => $ajeno->id, 'user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user)->get(route('torneos.mis-equipos'))
            ->assertOk()
            ->assertSee('Equipo Propio')
            ->assertSee('Equipo Ajeno');
    }

    public function test_usuario_capitan_de_un_equipo_y_jugador_de_otro(): void
    {
        $user = $this->torneoUser();
        $propio = $this->createClub($user, 'Mío');

        $otro  = $this->torneoUser();
        $ajeno = $this->createClub($otro, 'Ajeno');
        $this->actingAs($otro)->post(route('torneos.clubes.players.add', $ajeno), ['email' => $user->email])->assertRedirect();

        $this->assertTrue($propio->fresh()->isCaptainedBy($user));
        $memberRow = ClubPlayer::where('club_id', $ajeno->id)->where('user_id', $user->id)->first();
        $this->assertNotNull($memberRow);
        $this->assertFalse($memberRow->isCaptain());
    }

    // ─── Plantilla permanente: jugadores sin cuenta + anti-dup ────────────────

    public function test_capitan_da_de_alta_jugador_sin_cuenta(): void
    {
        $user = $this->torneoUser();
        $club = $this->createClub($user, 'Reales');
        $usuariosAntes = User::count();

        $this->actingAs($user)->post(route('torneos.clubes.players.addGuest', $club), [
            'full_name' => 'Maradona Sin Cuenta', 'document' => 'DOC-555',
        ])->assertRedirect();

        $this->assertEquals($usuariosAntes, User::count());
        $this->assertDatabaseHas('club_players', [
            'club_id' => $club->id, 'user_id' => null,
            'full_name' => 'Maradona Sin Cuenta', 'verification_status' => 'por_verificar',
        ]);
    }

    public function test_anti_dup_usuario_en_plantilla(): void
    {
        $user = $this->torneoUser();
        $club = $this->createClub($user, 'AntiDup');
        $jugador = $this->torneoUser();

        // v2.0 (E6/H9): alta por user_id (sugerencia de búsqueda por nombre).
        $this->actingAs($user)->post(route('torneos.clubes.players.add', $club), ['user_id' => $jugador->id])->assertRedirect();
        $this->actingAs($user)->post(route('torneos.clubes.players.add', $club), ['user_id' => $jugador->id])->assertSessionHasErrors('user_id');

        $this->assertEquals(1, ClubPlayer::where('club_id', $club->id)->where('user_id', $jugador->id)->count());
    }

    public function test_anti_dup_documento_en_plantilla(): void
    {
        $user = $this->torneoUser();
        $club = $this->createClub($user, 'AntiDoc');

        $this->actingAs($user)->post(route('torneos.clubes.players.addGuest', $club), ['full_name' => 'Uno', 'document' => 'CC-1'])->assertRedirect();
        $this->actingAs($user)->post(route('torneos.clubes.players.addGuest', $club), ['full_name' => 'Dos', 'document' => 'CC-1'])->assertSessionHasErrors('document');

        $this->assertEquals(1, ClubPlayer::where('club_id', $club->id)->where('document', 'CC-1')->count());
    }

    // ─── Capitanía ────────────────────────────────────────────────────────────

    public function test_no_se_puede_quitar_al_capitan(): void
    {
        $user = $this->torneoUser();
        $club = $this->createClub($user, 'Intocable');
        $cap  = ClubPlayer::where('club_id', $club->id)->where('user_id', $user->id)->firstOrFail();

        $this->actingAs($user)->delete(route('torneos.clubes.players.remove', [$club, $cap]))->assertRedirect();

        $this->assertDatabaseHas('club_players', ['id' => $cap->id]);
    }

    public function test_cambiar_capitan_a_otro_miembro(): void
    {
        $user  = $this->torneoUser();
        $club  = $this->createClub($user, 'Relevo');
        $nuevo = $this->torneoUser();
        $this->actingAs($user)->post(route('torneos.clubes.players.add', $club), ['email' => $nuevo->email])->assertRedirect();

        $this->actingAs($user)->patch(route('torneos.clubes.captain', $club), ['user_id' => $nuevo->id])->assertRedirect();

        $this->assertEquals($nuevo->id, $club->fresh()->captain_user_id);
        $this->assertDatabaseHas('club_players', ['club_id' => $club->id, 'user_id' => $nuevo->id, 'is_captain' => true]);
        $this->assertDatabaseHas('club_players', ['club_id' => $club->id, 'user_id' => $user->id, 'is_captain' => false]);
    }

    // ─── Enrolamiento a torneos ───────────────────────────────────────────────

    public function test_enrolar_equipo_copia_plantilla_y_aprueba_a_todos(): void
    {
        $admin = User::factory()->create(['role' => 'user',]);
        $user  = $this->torneoUser();
        $club  = $this->createClub($user, 'Enrolado');
        $this->actingAs($user)->post(route('torneos.clubes.players.addGuest', $club), ['full_name' => 'Invitado X', 'document' => 'D-9']);

        $t = $this->makeTournament($admin, 'open');

        $this->actingAs($user)->post(route('torneos.equipo.store', $t), ['club_id' => $club->id])->assertRedirect();

        $team = \App\Models\Torneos\Team::where('tournament_id', $t->id)->where('club_id', $club->id)->firstOrFail();
        // Plantilla copiada (capitán + invitado), todos aprobados (active).
        $this->assertEquals(2, TeamPlayer::where('team_id', $team->id)->count());
        $this->assertEquals(2, TeamPlayer::where('team_id', $team->id)->where('status', 'active')->count());
    }

    public function test_jugador_agregado_con_torneo_en_curso_queda_pendiente(): void
    {
        $admin = User::factory()->create(['role' => 'user',]);
        $user  = $this->torneoUser();
        $club  = $this->createClub($user, 'EnCurso');

        // Enrolar con torneo abierto y luego pasar a in_progress.
        $t = $this->makeTournament($admin, 'open');
        $this->actingAs($user)->post(route('torneos.equipo.store', $t), ['club_id' => $club->id])->assertRedirect();
        $t->update(['status' => 'in_progress']);

        // Agregar un jugador nuevo a la plantilla permanente → pendiente en el torneo en curso.
        $nuevo = $this->torneoUser();
        $this->actingAs($user)->post(route('torneos.clubes.players.add', $club), ['email' => $nuevo->email])->assertRedirect();

        $team = \App\Models\Torneos\Team::where('tournament_id', $t->id)->where('club_id', $club->id)->firstOrFail();
        $this->assertDatabaseHas('team_players', [
            'team_id' => $team->id, 'user_id' => $nuevo->id, 'status' => 'pending',
        ]);
    }

    public function test_no_se_puede_editar_equipo_en_torneo_activo(): void
    {
        $admin = User::factory()->create(['role' => 'user',]);
        $user  = $this->torneoUser();
        $club  = $this->createClub($user, 'Bloqueado');

        $t = $this->makeTournament($admin, 'open');
        $this->actingAs($user)->post(route('torneos.equipo.store', $t), ['club_id' => $club->id])->assertRedirect();

        // Con el equipo participando en un torneo activo, no se puede renombrar.
        $this->actingAs($user)->patch(route('torneos.clubes.update', $club), ['name' => 'Nombre Nuevo'])
            ->assertRedirect();

        $this->assertEquals('Bloqueado', $club->fresh()->name);
    }
}
