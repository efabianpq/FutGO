<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\WelcomeNotification;
use Database\Seeders\InvitationCodeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class WelcomeNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InvitationCodeSeeder::class);
    }

    public function test_welcome_notification_se_envia_al_activar_codigo(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)
            ->post(route('activate.store'), ['code' => 'INV-001'])
            ->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue((bool) $user->is_active);

        Notification::assertSentTo($user, WelcomeNotification::class);
    }

    public function test_welcome_notification_no_se_envia_si_la_activacion_falla(): void
    {
        Notification::fake();

        $user = User::factory()->create(['is_active' => false]);
        $this->actingAs($user)
            ->post(route('activate.store'), ['code' => 'CODIGO-INVALIDO'])
            ->assertSessionHasErrors('code');

        $user->refresh();
        $this->assertFalse((bool) $user->is_active);

        Notification::assertNothingSent();
    }

    public function test_welcome_notification_contiene_datos_correctos(): void
    {
        $user = User::factory()->create([
            'name' => 'Carlos Pachón',
            'is_active' => false,
        ]);

        $notification = new WelcomeNotification();
        $mail = $notification->toMail($user)->toArray();

        $this->assertStringContainsString('Carlos', $mail['subject']);
        $this->assertStringContainsString('@SoyPachon', $mail['subject']);
        $this->assertStringContainsString('Carlos', $mail['greeting']);

        $body = collect($mail['introLines'])->implode("\n");
        $this->assertStringContainsString('104 partidos', $body);
        $this->assertStringContainsString('5 puntos', $body);
        $this->assertStringContainsString('5 minutos', $body);

        $this->assertStringContainsString('Pronósticos', $mail['actionText']);
        $this->assertStringContainsString('/predictions', $mail['actionUrl']);
    }
}
