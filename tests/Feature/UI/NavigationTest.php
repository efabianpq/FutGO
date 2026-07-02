<?php

namespace Tests\Feature\UI;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifica la navegación: sin diferenciación de rol/módulo, cualquier usuario
 * autenticado ve el menú completo de Torneos/Social.
 */
class NavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_autenticado_ve_menu_completo(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mi Carrera')
            ->assertSee('Mis Equipos')
            ->assertSee('Mis Torneos')
            ->assertSee('Buscar Torneo')
            ->assertSee('Ranking de la plataforma');
    }

    public function test_menu_perfil_siempre_visible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Configurar perfil')
            ->assertSee('Salir');
    }

    public function test_dashboard_renderiza_inicio(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tu semana');
    }

    public function test_inicio_redirige_al_nuevo_punto_de_entrada(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('inicio'))
            ->assertRedirect(route('dashboard'));
    }
}
