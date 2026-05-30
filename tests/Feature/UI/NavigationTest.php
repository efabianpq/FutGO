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
        $user = $this->userWithModules('polla');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Mis Pronósticos')
            ->assertSee('Ranking')
            ->assertDontSee('Mis Torneos');
    }

    public function test_usuario_solo_torneos_ve_menu_torneos_y_no_polla(): void
    {
        $user = $this->userWithModules('torneos');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Mis Torneos')
            ->assertDontSee('Mis Pronósticos')
            ->assertDontSee('Ranking');
    }

    public function test_usuario_con_ambos_modulos_ve_ambos_menus(): void
    {
        $user = $this->userWithModules('full');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Mis Pronósticos')
            ->assertSee('Mis Torneos');
    }

    public function test_usuario_sin_modulos_no_ve_menus_de_modulo(): void
    {
        $user = $this->userWithModules('');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Mis Pronósticos')
            ->assertDontSee('Mis Torneos')
            // Siempre disponibles
            ->assertSee('Inicio')
            ->assertSee('Perfil');
    }

    public function test_menus_generales_siempre_visibles(): void
    {
        $user = $this->userWithModules('full');

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Inicio')
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
