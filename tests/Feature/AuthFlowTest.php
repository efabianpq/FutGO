<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\InvitationCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InvitationCodeSeeder::class);
    }

    public function test_flujo_completo_registro_activacion_y_relogin(): void
    {
        // ---------- 1. Registrar un usuario nuevo ----------
        $response = $this->post(route('register.store'), [
            'nombre' => 'Lionel',
            'apellido' => 'Pachón',
            'email' => 'lionel.pachon@test.com',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
        ]);

        // 2. Confirmar que redirige a la pantalla de código de invitación
        $response->assertRedirect(route('activate.show'));
        $this->assertAuthenticated();

        $user = User::where('email', 'lionel.pachon@test.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Lionel Pachón', $user->name);
        $this->assertFalse((bool) $user->is_active);
        $this->assertNotNull($user->email_verified_at, 'email_verified_at debe setearse automáticamente');

        // Acceder a /dashboard estando inactivo debe redirigir a /activate
        $this->get(route('dashboard'))->assertRedirect(route('activate.show'));

        // ---------- 3. Ingresar el código INV-001 ----------
        $response = $this->post(route('activate.store'), ['code' => 'INV-001']);

        // 4. Confirmar que llega al dashboard
        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue((bool) $user->is_active);
        $this->assertSame('INV-001', $user->invitation_code);

        // Verifica que el código quedó marcado como usado en BD
        $invitation = DB::table('invitation_codes')->where('code', 'INV-001')->first();
        $this->assertTrue((bool) $invitation->is_used);
        $this->assertSame($user->id, (int) $invitation->used_by_user_id);
        $this->assertNotNull($invitation->used_at);

        // El dashboard ahora carga 200
        $this->get(route('dashboard'))->assertOk();

        // ---------- 5. Cerrar sesión ----------
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();

        // ---------- 6. Iniciar sesión nuevamente y ver que va directo al dashboard ----------
        $response = $this->post(route('login.store'), [
            'email' => 'lionel.pachon@test.com',
            'password' => 'SuperSecret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_codigo_invalido_muestra_error(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user);

        $response = $this->from(route('activate.show'))
            ->post(route('activate.store'), ['code' => 'NO-EXISTE']);

        $response->assertRedirect(route('activate.show'));
        $response->assertSessionHasErrors('code');

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);
    }

    public function test_codigo_ya_usado_no_se_puede_reutilizar(): void
    {
        // Primer usuario consume INV-002
        $userA = User::factory()->create(['is_active' => false]);
        $this->actingAs($userA)->post(route('activate.store'), ['code' => 'INV-002']);
        $this->post(route('logout'));

        // Segundo usuario intenta el mismo código
        $userB = User::factory()->create(['is_active' => false]);
        $this->actingAs($userB)
            ->post(route('activate.store'), ['code' => 'INV-002'])
            ->assertSessionHasErrors('code');

        $userB->refresh();
        $this->assertFalse((bool) $userB->is_active);
    }

    public function test_login_con_usuario_inactivo_redirige_a_activate(): void
    {
        $user = User::factory()->create([
            'email' => 'inactivo@test.com',
            'password' => 'Password123',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => 'inactivo@test.com',
            'password' => 'Password123',
        ])->assertRedirect(route('activate.show'));
    }
}
