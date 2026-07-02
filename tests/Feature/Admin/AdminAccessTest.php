<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_normal_es_redirigido_con_mensaje(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $res = $this->actingAs($user)->get('/admin');
        $res->assertRedirect(route('dashboard'));
        $res->assertSessionHas('status', fn ($m) => str_contains($m, 'No tienes permisos'));
    }

    public function test_guest_que_intenta_admin_es_redirigido_a_login(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_admin_accede_al_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_todas_las_rutas_admin_protegidas(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $this->actingAs($user);

        foreach ([
            route('admin.dashboard'),
            route('admin.users.index'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('dashboard'));
        }
    }
}
