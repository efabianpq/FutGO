<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\RegistrationConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'nombre'                => 'Carlos',
        'apellido'              => 'Prueba',
        'email'                 => 'carlos.prueba@test.com',
        'telefono'              => '3009876543',
        'password'              => 'Password123',
        'password_confirmation' => 'Password123',
    ];

    public function test_se_envia_confirmacion_al_registrarse_exitosamente(): void
    {
        Notification::fake();

        $this->post(route('register.store'), $this->validPayload)
            ->assertRedirect(route('torneos.mi-carrera'));

        $user = User::where('email', 'carlos.prueba@test.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, RegistrationConfirmationNotification::class);
    }

    public function test_registro_no_falla_si_el_email_falla(): void
    {
        Notification::fake();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP error'));

        $response = $this->post(route('register.store'), $this->validPayload);

        // El registro igual redirige a Mi Carrera — el error de email no bloquea
        $response->assertRedirect(route('torneos.mi-carrera'));

        $user = User::where('email', 'carlos.prueba@test.com')->first();
        $this->assertNotNull($user);
    }

    public function test_email_contiene_saludo_y_boton_de_ingreso(): void
    {
        $user = User::factory()->make([
            'name'  => 'María Test',
            'email' => 'maria@test.com',
        ]);

        $notification = new RegistrationConfirmationNotification($user);
        $mail = $notification->toMail($user)->toArray();

        // Asunto y saludo con nombre
        $this->assertStringContainsString('María', $mail['subject']);
        $this->assertStringContainsString('María', $mail['greeting']);

        // Botón de ingreso
        $this->assertStringContainsString('FutGO', $mail['actionText']);
        $this->assertStringContainsString('/dashboard', $mail['actionUrl']);
    }
}
