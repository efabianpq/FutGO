<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifica que las rutas de autenticación devuelven 429 al superar el límite.
 *
 * Cada test limpia la caché del throttle para partir de un estado limpio.
 */
class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    // ── Login ─────────────────────────────────────────────────────────────────

    public function test_login_devuelve_429_al_superar_5_intentos_por_minuto(): void
    {
        $payload = ['email' => 'alguien@test.com', 'password' => 'cualquiera'];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), $payload);
        }

        $this->post(route('login.store'), $payload)->assertStatus(429);
    }

    public function test_login_el_quinto_intento_no_es_bloqueado(): void
    {
        $payload = ['email' => 'alguien@test.com', 'password' => 'cualquiera'];

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login.store'), $payload);
        }

        // El 5to intento (dentro del límite) redirige con error de credenciales, no 429
        $response = $this->post(route('login.store'), $payload);
        $response->assertStatus(302); // redirect back con error de sesión
        $response->assertSessionHasErrors('email');
    }

    // ── Registro ──────────────────────────────────────────────────────────────

    public function test_registro_devuelve_429_al_superar_5_intentos_por_minuto(): void
    {
        $payload = [
            'nombre'                => 'Test',
            'apellido'              => 'Usuario',
            'email'                 => 'test@test.com',
            'telefono'              => '3001234567',
            'password'              => 'Password123',
            'password_confirmation' => 'Password123',
        ];

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('register.store'), array_merge($payload, [
                'email' => "test{$i}@test.com",
            ]));
        }

        $this->post(route('register.store'), array_merge($payload, [
            'email' => 'test99@test.com',
        ]))->assertStatus(429);
    }

    // ── Recuperación de contraseña ────────────────────────────────────────────

    public function test_forgot_password_devuelve_429_al_superar_3_intentos_por_minuto(): void
    {
        $payload = ['email' => 'alguien@test.com'];

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.email'), $payload);
        }

        $this->post(route('password.email'), $payload)->assertStatus(429);
    }

    public function test_reset_password_devuelve_429_al_superar_3_intentos_por_minuto(): void
    {
        $payload = [
            'token'                 => 'token-invalido',
            'email'                 => 'alguien@test.com',
            'password'              => 'NuevaClave123',
            'password_confirmation' => 'NuevaClave123',
        ];

        for ($i = 0; $i < 3; $i++) {
            $this->post(route('password.store'), $payload);
        }

        $this->post(route('password.store'), $payload)->assertStatus(429);
    }
}
