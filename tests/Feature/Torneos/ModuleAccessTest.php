<?php

namespace Tests\Feature\Torneos;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_polla_no_accede_a_torneos(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'polla',
        ]);
        $this->actingAs($user)
             ->get('/torneos')
             ->assertRedirect();
    }

    public function test_usuario_torneos_accede_a_torneos(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ]);
        $this->actingAs($user)
             ->get('/torneos')
             ->assertOk();
    }

    public function test_usuario_full_accede_a_torneos(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'full',
        ]);
        $this->actingAs($user)
             ->get('/torneos')
             ->assertOk();
    }

    public function test_admin_accede_a_torneos_sin_importar_modules(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'admin',
            'modules'   => 'polla',
        ]);
        $this->actingAs($user)
             ->get('/torneos')
             ->assertOk();
    }

    public function test_torneo_admin_accede_al_panel_de_torneos(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'torneo_admin',
            'modules'   => 'torneos',
        ]);
        $this->actingAs($user)
             ->get('/admin/torneos')
             ->assertOk();
    }

    public function test_usuario_normal_no_accede_al_panel_de_torneos(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ]);
        $this->actingAs($user)
             ->get('/admin/torneos')
             ->assertRedirect();
    }

    public function test_usuarios_polla_existentes_mantienen_acceso_a_predictions(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'polla',
        ]);
        $this->actingAs($user)
             ->get('/predictions')
             ->assertOk();
    }
}
