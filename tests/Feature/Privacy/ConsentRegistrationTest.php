<?php

namespace Tests\Feature\Privacy;

use App\Models\Privacy\UserConsent;
use App\Models\User;
use Database\Seeders\LegalDocumentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ConsentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LegalDocumentsSeeder::class);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'nombre'                => 'Ana',
            'apellido'              => 'Test',
            'email'                 => 'ana.test@example.com',
            'telefono'              => '3001234567',
            'birthdate'             => '1995-05-05',
            'password'              => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'accept_terms'          => '1',
            'accept_privacy'        => '1',
        ], $overrides);
    }

    public function test_registro_falla_si_no_acepta_terminos_o_privacidad(): void
    {
        $this->post(route('register.store'), $this->payload(['accept_terms' => null]))
            ->assertSessionHasErrors('accept_terms');

        $this->post(route('register.store'), $this->payload(['accept_privacy' => null]))
            ->assertSessionHasErrors('accept_privacy');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registro_falla_si_es_menor_a_la_edad_minima(): void
    {
        $this->post(route('register.store'), $this->payload([
            'birthdate' => now()->subYears(10)->toDateString(),
        ]))->assertSessionHasErrors('birthdate');

        $this->assertDatabaseCount('users', 0);
    }

    public function test_registro_exitoso_graba_consentimientos_y_configuracion(): void
    {
        $this->post(route('register.store'), $this->payload(['accept_marketing' => '1']))
            ->assertRedirect(route('torneos.mi-carrera'));

        $user = User::where('email', 'ana.test@example.com')->firstOrFail();

        // Consentimientos con versión + IP registrados.
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id, 'document_type' => 'terms', 'document_version' => '1.0', 'accepted' => true,
        ]);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id, 'document_type' => 'privacy', 'document_version' => '1.0', 'accepted' => true,
        ]);
        $this->assertDatabaseHas('user_consents', [
            'user_id' => $user->id, 'document_type' => 'marketing', 'accepted' => true,
        ]);
        $this->assertNotNull(UserConsent::where('user_id', $user->id)->first()->ip);

        // Configuración de privacidad creada con defaults.
        $this->assertDatabaseHas('privacy_settings', ['user_id' => $user->id, 'show_email' => false, 'searchable' => true]);

        // Cache de versiones aceptadas.
        $this->assertSame('1.0', $user->fresh()->current_privacy_version);
        $this->assertSame('1.0', $user->fresh()->current_terms_version);
    }

    public function test_registro_sin_marketing_no_graba_ese_consentimiento(): void
    {
        $this->post(route('register.store'), $this->payload())
            ->assertRedirect(route('torneos.mi-carrera'));

        $user = User::where('email', 'ana.test@example.com')->firstOrFail();
        $this->assertDatabaseMissing('user_consents', ['user_id' => $user->id, 'document_type' => 'marketing']);
    }

    public function test_menor_de_18_requiere_correo_del_representante(): void
    {
        // Menor de 18 pero mayor a la edad mínima (14) → requiere guardian_email.
        $this->post(route('register.store'), $this->payload([
            'birthdate' => now()->subYears(15)->toDateString(),
        ]))->assertSessionHasErrors('guardian_email');

        $this->post(route('register.store'), $this->payload([
            'birthdate'      => now()->subYears(15)->toDateString(),
            'guardian_email' => 'mama@example.com',
        ]))->assertRedirect(route('torneos.mi-carrera'));

        $user = User::where('email', 'ana.test@example.com')->firstOrFail();
        $this->assertTrue($user->pending_guardian_consent);
        $this->assertSame('mama@example.com', $user->guardian_email);
    }
}
