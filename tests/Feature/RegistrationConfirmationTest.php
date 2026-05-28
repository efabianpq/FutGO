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
            ->assertRedirect(route('activate.show'));

        $user = User::where('email', 'carlos.prueba@test.com')->first();
        $this->assertNotNull($user);

        Notification::assertSentTo($user, RegistrationConfirmationNotification::class);
    }

    public function test_registro_no_falla_si_el_email_falla(): void
    {
        Notification::fake();
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('SMTP error'));

        $response = $this->post(route('register.store'), $this->validPayload);

        // El registro igual redirige a /activate — el error de email no bloquea
        $response->assertRedirect(route('activate.show'));

        $user = User::where('email', 'carlos.prueba@test.com')->first();
        $this->assertNotNull($user);
        $this->assertFalse((bool) $user->is_active);
    }

    public function test_email_contiene_numero_whatsapp_y_pasos(): void
    {
        $user = User::factory()->make([
            'name'  => 'María Test',
            'email' => 'maria@test.com',
        ]);

        $notification = new RegistrationConfirmationNotification($user);
        $mail = $notification->toMail($user)->toArray();

        // Asunto con nombre
        $this->assertStringContainsString('María', $mail['subject']);

        // Número de WhatsApp en el cuerpo
        $body = collect($mail['introLines'])->implode("\n");
        $this->assertStringContainsString('3013966515', $body);

        // Los 3 pasos presentes
        $this->assertStringContainsString('Hacé la transferencia', $body);
        $this->assertStringContainsString('comprobante', $body);
        $this->assertStringContainsString('código de activación', $body);

        // Email del usuario inyectado en el paso 2
        $this->assertStringContainsString('maria@test.com', $body);

        // Botón a /login
        $this->assertStringContainsString('Iniciar sesión', $mail['actionText']);
        $this->assertStringContainsString('/login', $mail['actionUrl']);
    }
}
