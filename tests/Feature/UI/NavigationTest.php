<?php

namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica la navegación modular: el navbar solo muestra los enlaces de los
 * módulos habilitados para el usuario autenticado.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithModules(string $modules): User
    {
        return User::factory()->create([
            'is_active' => true,
            'modules'   => $modules,
        ]);
    }

    public function test_usuario_solo_polla_ve_menu_polla_y_no_torneos(): void
    {
        // v2.0: "Pronósticos" es un dropdown que agrupa Mis Pronósticos, Auditoría, etc.
        $user = $this->userWithModules('polla');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Pronósticos')      // botón del dropdown
            ->assertSee('Auditoría')        // subitem exclusivo de la polla
            ->assertDontSee('Mis Torneos')
            ->assertDontSee('Mi Carrera');
    }

    public function test_usuario_solo_torneos_ve_menu_torneos_y_no_polla(): void
    {
        $user = $this->userWithModules('torneos');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mi Carrera')
            ->assertSee('Mis Equipos')
            ->assertSee('Mis Torneos')
            ->assertSee('Buscar Torneo')        // antes "Torneos" (v2.0)
            ->assertSee('Ranking de la plataforma')
            ->assertDontSee('Pronósticos')
            ->assertDontSee('Auditoría');   // discriminador exclusivo de la polla
    }

    public function test_usuario_con_ambos_modulos_ve_ambos_menus(): void
    {
        $user = $this->userWithModules('full');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Pronósticos')      // polla
            ->assertSee('Auditoría')        // polla
            ->assertSee('Mi Carrera')       // torneos
            ->assertSee('Mis Torneos');     // torneos
    }

    public function test_usuario_sin_modulos_no_ve_menus_de_modulo(): void
    {
        $user = $this->userWithModules('');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('Pronósticos')
            ->assertDontSee('Mis Torneos')
            ->assertDontSee('Mi Carrera')
            // Menú de perfil siempre disponible.
            ->assertSee('Configurar perfil');
    }

    public function test_menu_perfil_siempre_visible(): void
    {
        $user = $this->userWithModules('full');

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Configurar perfil')
            ->assertSee('Salir');
    }

    public function test_dashboard_redirige_segun_modulo_polla(): void
    {
        $user = $this->userWithModules('polla');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('predictions.index'));
    }

    public function test_dashboard_torneos_renderiza_inicio(): void
    {
        // v3: /dashboard es el Inicio (dashboard) para usuarios de torneos.
        $user = $this->userWithModules('torneos');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tu semana');
    }

    public function test_inicio_redirige_al_nuevo_punto_de_entrada(): void
    {
        $user = $this->userWithModules('torneos');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertRedirect(route('dashboard'));
    }
}
