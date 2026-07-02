<?php

namespace Tests\Feature\Privacy;

use App\Models\User;
use App\Notifications\Privacy\GuardianConsentNotification;
use App\Services\Privacy\ParentalConsentService;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MinorParentalConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalDocumentsSeeder::class);
        config()->set('privacy.parental_consent', true);
    }

    private function registerMinor(): User
    {
        Notification::fake();

        $this->post(route('register.store'), [
            'nombre' => 'Nico', 'apellido' => 'Junior',
            'email' => 'nico@example.com', 'telefono' => '3001234567',
            'birthdate' => now()->subYears(15)->toDateString(),
            'guardian_email' => 'mama@example.com',
            'password' => 'SuperSecret123', 'password_confirmation' => 'SuperSecret123',
            'accept_terms' => '1', 'accept_privacy' => '1',
        ])->assertRedirect();

        return User::where('email', 'nico@example.com')->firstOrFail();
    }

    public function test_registro_de_menor_envia_correo_al_representante(): void
    {
        $minor = $this->registerMinor();

        $this->assertTrue($minor->pending_guardian_consent);
        Notification::assertSentOnDemand(GuardianConsentNotification::class);
    }

    public function test_cuenta_pendiente_no_puede_publicar_oportunidad(): void
    {
        $minor = $this->registerMinor();

        $this->actingAs($minor)->post(route('social.oportunidades.store'), [])
            ->assertRedirect(route('parental.pending'));
    }

    public function test_confirmacion_firmada_activa_la_cuenta(): void
    {
        $minor = $this->registerMinor();

        $url = app(ParentalConsentService::class)->confirmationUrl($minor);
        $this->get($url)->assertOk();

        $this->assertFalse($minor->fresh()->pending_guardian_consent);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $minor->id, 'document_type' => 'parental', 'accepted' => true,
        ]);

        // Ya puede operar (no lo redirige el middleware de guardián).
        $this->actingAs($minor->fresh())->post(route('social.oportunidades.store'), [])
            ->assertSessionHasErrors(); // llega al controlador (falla por validación, no por el guardián)
    }

    public function test_enlace_con_firma_invalida_es_rechazado(): void
    {
        $minor = $this->registerMinor();

        $this->get(route('parental.confirm', ['user' => $minor->id]))->assertForbidden();
    }
}
