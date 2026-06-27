<?php

namespace Tests\Feature\Torneos;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Verifica que /torneos/validar aplica throttle (30/min por usuario).
 */
class CredentialRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private function makeReferee(): User
    {
        return User::factory()->create([
            'is_active' => true,
            'role'      => 'torneo_admin',
            'modules'   => 'torneos',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_validar_devuelve_429_al_superar_30_intentos_por_minuto(): void
    {
        $referee = $this->makeReferee();
        $payload = ['fg' => 'FG-ZZZZZZ'];

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($referee)->post(route('torneos.validar.run'), $payload);
        }

        $this->actingAs($referee)
            ->post(route('torneos.validar.run'), $payload)
            ->assertStatus(429);
    }

    public function test_validar_acepta_hasta_30_intentos_sin_bloquear(): void
    {
        $referee = $this->makeReferee();
        $payload = ['fg' => 'FG-ZZZZZZ'];

        // El intento 30 aún debe responder normalmente (no 429)
        for ($i = 0; $i < 29; $i++) {
            $this->actingAs($referee)->post(route('torneos.validar.run'), $payload);
        }

        $this->actingAs($referee)
            ->post(route('torneos.validar.run'), $payload)
            ->assertOk();
    }
}
