<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_flujo_completo_registro_y_relogin(): void
    {
        // ---------- 1. Registrar un usuario nuevo ----------
        $response = $this->post(route('register.store'), [
            'nombre' => 'Lionel',
            'apellido' => 'Pachón',
            'email' => 'lionel.pachon@test.com',
            'telefono' => '3001234567',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
        ]);

        // 2. Queda logueado y con acceso inmediato, sin código de activación.
        $response->assertRedirect(route('torneos.mi-carrera'));
        $this->assertAuthenticated();

        $user = User::where('email', 'lionel.pachon@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Lionel Pachón', $user->name);
        $this->assertNotNull($user->email_verified_at, 'email_verified_at debe setearse automáticamente');

        // Acceso inmediato al dashboard, sin gate intermedio.
        $this->get(route('dashboard'))->assertOk();

        // ---------- 3. Cerrar sesión ----------
        // Logout redirige a home (no a login) para que el usuario vea la página principal
        $this->post(route('logout'))->assertRedirect(route('home'));
        $this->assertGuest();

        // ---------- 4. Iniciar sesión nuevamente ----------
        $response = $this->post(route('login.store'), [
            'email' => 'lionel.pachon@test.com',
            'password' => 'SuperSecret123',
        ]);

        $response->assertRedirect(route('torneos.mi-carrera'));
        $this->assertAuthenticated();
    }
}
