<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use App\Services\Privacy\LegalDocumentService;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReconsentMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalDocumentsSeeder::class);
    }

    private function upToDateUser(): User
    {
        return User::factory()->create([
            'current_privacy_version' => '1.0',
            'current_terms_version'   => '1.0',
        ]);
    }

    public function test_usuario_al_dia_navega_normal(): void
    {
        $this->actingAs($this->upToDateUser())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_nueva_version_fuerza_reaceptacion_y_bloquea_navegacion(): void
    {
        $user = $this->upToDateUser();

        app(LegalDocumentService::class)->publish([
            'type' => 'privacy', 'version' => '1.1', 'title' => 'Política de privacidad',
            'content' => 'Contenido v1.1', 'summary_of_changes' => 'Aclaramos el uso de cookies.',
        ]);

        // Cualquier ruta protegida redirige a la pantalla de re-aceptación.
        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('privacidad.aceptar'));

        // La pantalla de re-aceptación sí es accesible.
        $this->actingAs($user)->get(route('privacidad.aceptar'))->assertOk();

        // Aceptar actualiza el cache y desbloquea.
        $this->actingAs($user)->post(route('privacidad.aceptar.store'), ['accept' => '1'])
            ->assertRedirect(route('inicio'));

        $this->assertSame('1.1', $user->fresh()->current_privacy_version);
        $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();
    }
}
