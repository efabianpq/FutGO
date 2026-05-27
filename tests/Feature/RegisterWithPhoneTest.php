<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterWithPhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_telefono_es_obligatorio(): void
    {
        $this->post(route('register.store'), [
            'nombre' => 'Juan', 'apellido' => 'Pérez',
            'email' => 'juan@test.com',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            // sin telefono
        ])->assertSessionHasErrors('telefono');
    }

    public function test_telefono_acepta_solo_numeros_7_a_15_digitos(): void
    {
        $base = [
            'nombre' => 'Juan', 'apellido' => 'Pérez',
            'email' => 'juan@test.com',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
        ];

        // Solo 6 dígitos: error
        $this->post(route('register.store'), $base + ['telefono' => '123456'])
            ->assertSessionHasErrors('telefono');

        // Con letras: error
        $this->post(route('register.store'), $base + ['telefono' => '300ABC1234'])
            ->assertSessionHasErrors('telefono');

        // 16 dígitos: error
        $this->post(route('register.store'), $base + ['telefono' => '1234567890123456'])
            ->assertSessionHasErrors('telefono');

        // Válido (10 dígitos): pasa
        $this->post(route('register.store'), $base + ['telefono' => '3001234567'])
            ->assertRedirect(route('activate.show'));

        $u = User::where('email', 'juan@test.com')->first();
        $this->assertNotNull($u);
        $this->assertSame('3001234567', $u->phone_whatsapp);
    }

    public function test_perfil_permite_editar_telefono(): void
    {
        $u = User::factory()->create([
            'is_active' => true,
            'phone_whatsapp' => '3001111111',
        ]);

        $this->actingAs($u)->patch(route('profile.update'), [
            'phone_whatsapp' => '3009999999',
        ])->assertRedirect();

        $u->refresh();
        $this->assertSame('3009999999', $u->phone_whatsapp);
    }
}
