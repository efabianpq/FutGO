<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gestión del equipo permanente por su capitán (antes "Panel del Capitán",
 * ahora unificado en "Mis Equipos" + gestión del club).
 */
class CaptainDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            [],
            $attrs
        ));
    }

    /** Equipo permanente con su capitán y plantilla mínima. */
    private function makeClub(User $captain): Club
    {
        $club = Club::create([
            'name'               => 'Los Cracks',
            'slug'               => 'los-cracks-' . uniqid(),
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
        ]);
        ClubPlayer::create([
            'club_id' => $club->id, 'user_id' => $captain->id, 'is_captain' => true, 'status' => 'active',
        ]);
        return $club;
    }

    public function test_capitan_ve_su_equipo_en_mis_equipos(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);

        $this->actingAs($captain)
            ->get(route('torneos.mis-equipos'))
            ->assertOk()
            ->assertSee('Mis Equipos')
            ->assertSee($club->name);
    }

    public function test_capitan_puede_gestionar_plantilla(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        ClubPlayer::create([
            'club_id' => $club->id, 'user_id' => $this->makeUser(['name' => 'Jugador Plantilla'])->id, 'status' => 'active',
        ]);

        $this->actingAs($captain)
            ->get(route('torneos.clubes.manage', $club))
            ->assertOk()
            ->assertSee('Plantilla')
            ->assertSee('Jugador Plantilla');
    }

    public function test_no_capitan_no_puede_gestionar_equipo_ajeno(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);

        $otro = $this->makeUser(['name' => 'Ajeno']);

        $this->actingAs($otro)
            ->get(route('torneos.clubes.manage', $club))
            ->assertForbidden();
    }

    public function test_navbar_muestra_mis_equipos(): void
    {
        $captain = $this->makeUser();
        $this->makeClub($captain);

        $this->actingAs($captain)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mis Equipos');
    }

    // ─── E8/H13: el admin de plataforma ve todos los equipos ─────────────────────

    public function test_admin_de_plataforma_ve_todos_los_equipos(): void
    {
        // Equipos de distintos capitanes.
        $cap1 = $this->makeUser(['name' => 'Capitán Uno']);
        $club1 = $this->makeClub($cap1);
        $club1->update(['name' => 'Equipo Alfa']);

        $cap2 = $this->makeUser(['name' => 'Capitán Dos']);
        $club2 = $this->makeClub($cap2);
        $club2->update(['name' => 'Equipo Beta']);

        $platformAdmin = $this->makeUser(['role' => 'admin']);

        $this->actingAs($platformAdmin)
            ->get(route('torneos.mis-equipos'))
            ->assertOk()
            ->assertSee('Todos los equipos')   // título para admin
            ->assertSee('Equipo Alfa')
            ->assertSee('Equipo Beta');
    }

    // ─── E6/H9: agregar jugador por nombre con autocompletado ────────────────────

    public function test_busqueda_de_jugadores_por_nombre_parcial(): void
    {
        $captain = $this->makeUser();
        $this->makeClub($captain);

        // "Edisson Fabian Pachon" debe aparecer al buscar "Fabian".
        $this->makeUser(['name' => 'Edisson Fabian Pachon']);
        $this->makeUser(['name' => 'Carlos Gómez']);

        $res = $this->actingAs($captain)
            ->getJson(route('torneos.jugadores.buscar', ['q' => 'fabian']));

        $res->assertOk();
        $res->assertJsonFragment(['name' => 'Edisson Fabian Pachon']);
        $res->assertJsonMissing(['name' => 'Carlos Gómez']);
    }

    public function test_busqueda_requiere_minimo_dos_caracteres(): void
    {
        $captain = $this->makeUser();
        $this->makeClub($captain);
        $this->makeUser(['name' => 'Fabian']);

        $this->actingAs($captain)
            ->getJson(route('torneos.jugadores.buscar', ['q' => 'f']))
            ->assertOk()
            ->assertJsonCount(0);
    }

    public function test_agregar_jugador_por_user_id_desde_sugerencia(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $player  = $this->makeUser(['name' => 'Edisson Fabian Pachon']);

        $this->actingAs($captain)
            ->post(route('torneos.clubes.players.add', $club), [
                'user_id'  => $player->id,
                'position' => 'Delantero',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('club_players', [
            'club_id' => $club->id,
            'user_id' => $player->id,
        ]);
    }

    public function test_agregar_jugador_sin_seleccion_falla(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);

        $this->actingAs($captain)
            ->post(route('torneos.clubes.players.add', $club), ['position' => 'Arquero'])
            ->assertSessionHasErrors('user_id');
    }
}
