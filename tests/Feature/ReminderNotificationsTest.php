<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\MatchNotification;
use App\Models\Prediction;
use App\Models\User;
use App\Notifications\PredictionReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function gameInWindow(int $minutesUntilLock = 10): Game
    {
        return Game::create([
            'phase' => 'grupos', 'group_name' => 'A', 'match_number' => 9001,
            'home_team' => 'Local', 'away_team' => 'Visita',
            'home_flag' => '🏳️', 'away_flag' => '🏳️',
            'match_datetime' => now()->addMinutes($minutesUntilLock + 5),
            'lock_datetime' => now()->addMinutes($minutesUntilLock),
            'venue' => 'Test', 'status' => 'upcoming',
        ]);
    }

    public function test_envia_recordatorio_a_usuario_activo_sin_pronostico(): void
    {
        Notification::fake();

        $game = $this->gameInWindow(10);
        $user = User::factory()->create([
            'is_active' => true,
            'notifications_enabled' => true,
        ]);

        $this->artisan('notifications:reminders')->assertSuccessful();

        Notification::assertSentTo($user, PredictionReminderNotification::class,
            fn ($n) => $n->game->id === $game->id);

        // Marca de envío en BD
        $this->assertDatabaseHas('match_notifications', [
            'user_id' => $user->id,
            'match_id' => $game->id,
            'type' => MatchNotification::TYPE_REMINDER,
        ]);
    }

    public function test_no_envia_si_usuario_ya_tiene_pronostico(): void
    {
        Notification::fake();

        $game = $this->gameInWindow(10);
        $user = User::factory()->create(['is_active' => true, 'notifications_enabled' => true]);

        Prediction::create([
            'user_id' => $user->id, 'match_id' => $game->id,
            'home_score' => 2, 'away_score' => 1,
        ]);

        $this->artisan('notifications:reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_no_envia_si_usuario_desactivo_notificaciones(): void
    {
        Notification::fake();

        $game = $this->gameInWindow(10);
        User::factory()->create([
            'is_active' => true,
            'notifications_enabled' => false,
        ]);

        $this->artisan('notifications:reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_no_envia_a_usuarios_inactivos(): void
    {
        Notification::fake();

        $game = $this->gameInWindow(10);
        User::factory()->create([
            'is_active' => false,
            'notifications_enabled' => true,
        ]);

        $this->artisan('notifications:reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_no_envia_partidos_fuera_de_la_ventana_de_15_min(): void
    {
        Notification::fake();

        $tooFar = $this->gameInWindow(30); // 30 min, fuera de ventana
        $tooFar->match_number = 9002;
        $tooFar->save();

        User::factory()->create(['is_active' => true, 'notifications_enabled' => true]);

        $this->artisan('notifications:reminders')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_es_idempotente_si_se_corre_dos_veces(): void
    {
        Notification::fake();

        $game = $this->gameInWindow(10);
        $user = User::factory()->create(['is_active' => true, 'notifications_enabled' => true]);

        $this->artisan('notifications:reminders')->assertSuccessful();
        Notification::assertSentToTimes($user, PredictionReminderNotification::class, 1);

        // Segunda corrida — no debe reenviar
        $this->artisan('notifications:reminders')->assertSuccessful();
        Notification::assertSentToTimes($user, PredictionReminderNotification::class, 1);

        $this->assertSame(1, MatchNotification::where([
            'user_id' => $user->id, 'match_id' => $game->id,
            'type' => MatchNotification::TYPE_REMINDER,
        ])->count());
    }

    public function test_email_tiene_asunto_y_contenido_esperado(): void
    {
        $user = User::factory()->create([
            'is_active' => true, 'notifications_enabled' => true,
            'name' => 'María Test',
        ]);
        $game = Game::create([
            'phase' => 'grupos', 'group_name' => 'C', 'match_number' => 9050,
            'home_team' => 'Brasil', 'away_team' => 'Colombia',
            'home_flag' => '🇧🇷', 'away_flag' => '🇨🇴',
            'match_datetime' => now()->addMinutes(20),
            'lock_datetime' => now()->addMinutes(15),
            'venue' => 'Test', 'status' => 'upcoming',
        ]);

        $notification = new PredictionReminderNotification($game);
        $mail = $notification->toMail($user)->toArray();

        $this->assertStringContainsString('Faltan 15 minutos', $mail['subject']);
        $this->assertStringContainsString('Soy Pachón Mundial', $mail['subject']);
        $this->assertStringContainsString('María', $mail['greeting']);

        $body = collect($mail['introLines'])->implode("\n");
        $this->assertStringContainsString('Brasil', $body);
        $this->assertStringContainsString('Colombia', $body);
        $this->assertStringContainsString('0 puntos', $body);

        $this->assertStringContainsString('Mis Pronósticos', $mail['actionText']);
        $this->assertStringContainsString('/predictions', $mail['actionUrl']);
    }
}
