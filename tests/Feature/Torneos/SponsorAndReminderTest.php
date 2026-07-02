<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentMatchNotification;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Notifications\MatchReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Sesión G — recordatorios de partidos próximos + patrocinadores del torneo.
 */
class SponsorAndReminderTest extends TestCase
{
    use RefreshDatabase;

    /** Torneo en curso con un partido programado mañana y un jugador convocado. */
    private function upcomingMatchScenario(bool $notificationsEnabled = true): array
    {
        $admin = User::factory()->create(['role' => 'user',]);
        $tournament = Tournament::create([
            'name' => 'Copa G ' . uniqid(), 'slug' => 'copa-g-' . uniqid(),
            'sport' => 'futbol', 'status' => 'in_progress', 'format' => 'round_robin',
            'groups_count' => 1, 'teams_per_group' => 2, 'classifies_per_group' => 1, 'max_teams' => 2,
            'points_win' => 3, 'points_draw' => 1, 'points_loss' => 0, 'created_by_user_id' => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $phase = TournamentPhase::create(['tournament_id' => $tournament->id, 'name' => 'Grupos', 'type' => 'groups', 'order' => 1, 'is_active' => true, 'status' => 'active']);
        $home = Team::create(['tournament_id' => $tournament->id, 'captain_user_id' => $admin->id, 'name' => 'Local', 'status' => 'approved']);
        $away = Team::create(['tournament_id' => $tournament->id, 'captain_user_id' => $admin->id, 'name' => 'Visita', 'status' => 'approved']);

        $player = User::factory()->create(['notifications_enabled' => $notificationsEnabled]);
        $tp = TeamPlayer::create(['team_id' => $home->id, 'user_id' => $player->id, 'status' => 'active']);

        $match = TournamentMatch::create([
            'phase_id' => $phase->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'status' => 'scheduled', 'scheduled_at' => now()->addHours(10), 'match_number' => 1,
        ]);
        MatchCallUp::create(['match_id' => $match->id, 'team_player_id' => $tp->id, 'team_id' => $home->id, 'status' => 'convocado']);

        return compact('tournament', 'admin', 'player', 'match');
    }

    // ── Recordatorios ───────────────────────────────────────────────────────

    public function test_el_comando_envia_recordatorios_a_convocados_de_partidos_proximos(): void
    {
        Notification::fake();
        ['player' => $player, 'match' => $match] = $this->upcomingMatchScenario();

        $this->artisan('torneos:match-reminders')->assertSuccessful();

        Notification::assertSentTo($player, MatchReminderNotification::class);
        $this->assertDatabaseHas('tournament_match_notifications', [
            'user_id' => $player->id, 'match_id' => $match->id, 'type' => 'reminder',
        ]);
    }

    public function test_no_se_reenvia_dos_veces_el_mismo_recordatorio(): void
    {
        Notification::fake();
        ['player' => $player, 'match' => $match] = $this->upcomingMatchScenario();

        $this->artisan('torneos:match-reminders')->assertSuccessful();
        $this->artisan('torneos:match-reminders')->assertSuccessful();   // segunda corrida

        // Solo una notificación y una fila de control (idempotencia).
        Notification::assertSentToTimes($player, MatchReminderNotification::class, 1);
        $this->assertSame(1, TournamentMatchNotification::where('match_id', $match->id)->where('user_id', $player->id)->count());
    }

    public function test_un_usuario_con_notificaciones_desactivadas_no_recibe_recordatorio(): void
    {
        Notification::fake();
        ['player' => $player] = $this->upcomingMatchScenario(notificationsEnabled: false);

        $this->artisan('torneos:match-reminders')->assertSuccessful();

        Notification::assertNotSentTo($player, MatchReminderNotification::class);
        $this->assertSame(0, TournamentMatchNotification::count());
    }

    // ── Patrocinadores ───────────────────────────────────────────────────────

    public function test_los_patrocinadores_se_asocian_y_aparecen_en_el_portal_publico(): void
    {
        ['tournament' => $t, 'admin' => $admin] = $this->upcomingMatchScenario();
        $t->update(['visibility' => 'public']);

        // El admin agrega un patrocinador desde el panel.
        $this->actingAs($admin)
            ->post(route('admin.torneos.sponsors.store', $t), [
                'name' => 'Deportes Acme', 'link_url' => 'https://acme.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tournament_sponsors', ['tournament_id' => $t->id, 'name' => 'Deportes Acme']);

        // Aparece en el portal público (sin login).
        $this->get(route('torneos.public.show', $t))
            ->assertOk()
            ->assertSee('Deportes Acme')
            ->assertSee('Patrocinan este torneo');
    }

    // ── Scheduler ─────────────────────────────────────────────────────────────

    public function test_el_scheduler_de_torneos_esta_registrado(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $commands = collect($schedule->events())->map(fn ($e) => $e->command ?? '')->implode(' ');

        $this->assertStringContainsString('torneos:match-reminders', $commands);
    }
}
