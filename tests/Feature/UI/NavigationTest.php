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
        // H2/H17: el label cambió de "Mis Pronósticos" → "Pronósticos"
        $user = $this->userWithModules('polla');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Pronósticos')      // nuevo label (sin "Mis")
            ->assertSee('Auditoría')        // discriminador exclusivo de la polla
            ->assertDontSee('Mis Torneos')
            ->assertDontSee('Mi Carrera');
    }

    public function test_usuario_solo_torneos_ve_menu_torneos_y_no_polla(): void
    {
        $user = $this->userWithModules('torneos');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Mi Carrera')
            ->assertSee('Mis Equipos')
            ->assertSee('Mis Torneos')
            ->assertSee('Ranking')          // el módulo Torneos tiene su propio ranking
            ->assertDontSee('Pronósticos')
            ->assertDontSee('Auditoría');   // discriminador exclusivo de la polla
    }

    public function test_usuario_con_ambos_modulos_ve_ambos_menus(): void
    {
        $user = $this->userWithModules('full');

        $this->actingAs($user)
            ->get(route('inicio'))
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
            ->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Pronósticos')
            ->assertDontSee('Mis Torneos')
            ->assertDontSee('Mi Carrera')
            // Siempre disponibles
            ->assertSee('Perfil');
    }

    public function test_menus_generales_siempre_visibles(): void
    {
        $user = $this->userWithModules('full');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Perfil');
    }

    public function test_dashboard_redirige_segun_modulo_polla(): void
    {
        $user = $this->userWithModules('polla');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('predictions.index'));
    }

    public function test_dashboard_redirige_torneos_a_inicio(): void
    {
        $user = $this->userWithModules('torneos');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('inicio'));
    }
}
