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
            ['is_active' => true, 'modules' => 'torneos'],
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
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Mis Equipos');
    }
}
