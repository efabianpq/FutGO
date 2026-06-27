<?php

namespace Tests\Feature\Social;

use App\Console\Commands\Social\DetectNoShows;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\ReliabilityEvent;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\User;
use App\Services\Social\ReliabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FutGO Social — Fase 1 · Sesión S1-D: score de confiabilidad, no-shows
 * automáticos, pausa y reactivación manual.
 *
 * Cubre:
 *  1. Score baja con no_show
 *  2. Score sube con calificacion_positiva
 *  3. Score combina positivos y negativos correctamente
 *  4. Detección automática de no-show por scheduler
 *  5. Pausa automática al segundo no-show en 30 días
 *  6. No-show fuera de la ventana de 30d no activa pausa
 *  7. Reactivación manual desbloquea publicar
 *  8. No-show idempotente (no duplica si ya existe)
 *  9. Score para club funciona igual que para usuario
 * 10. Rebuild procesa todos los sujetos
 */
class ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'User ' . uniqid(),
            'email'     => uniqid('rel') . '@test.com',
            'password'  => bcrypt('pass'),
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ], $extra));
    }

    private function makeClub(User $captain): Club
    {
        return Club::create([
            'name'               => 'Club ' . uniqid(),
            'slug'               => uniqid('c-'),
            'status'             => 'validado',
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
        ]);
    }

    private function event(string $subjectType, int $subjectId, string $type, Carbon $at = null, ?int $matchId = null): ReliabilityEvent
    {
        return ReliabilityEvent::create([
            'subject_type'     => $subjectType,
            'subject_id'       => $subjectId,
            'type'             => $type,
            'friendly_match_id' => $matchId,
            'occurred_at'      => $at ?? now(),
        ]);
    }

    private function service(): ReliabilityService
    {
        return app(ReliabilityService::class);
    }

    // ── 1. Score baja con no_show ────────────────────────────────────────

    public function test_score_baja_con_no_show(): void
    {
        $user = $this->makeUser();

        $this->event('user', $user->id, ReliabilityEvent::TYPE_NO_SHOW);

        $score = $this->service()->refreshForUser($user);

        $expectedScore = 100 + ReliabilityService::WEIGHTS[ReliabilityEvent::TYPE_NO_SHOW]; // 80
        $this->assertSame($expectedScore, $score->score);
        $this->assertSame(1, $score->no_shows);
    }

    // ── 2. Score sube con calificacion_positiva ──────────────────────────

    public function test_score_sube_con_calificacion_positiva(): void
    {
        $user = $this->makeUser();

        $this->event('user', $user->id, ReliabilityEvent::TYPE_CALIFICACION_POSITIVA);

        $score = $this->service()->refreshForUser($user);

        $expected = min(100, 100 + ReliabilityService::WEIGHTS[ReliabilityEvent::TYPE_CALIFICACION_POSITIVA]);
        $this->assertSame($expected, $score->score);
        $this->assertSame(1, $score->positive_ratings);
    }

    // ── 3. Score combina positivos y negativos ───────────────────────────

    public function test_score_combina_eventos(): void
    {
        $user = $this->makeUser();

        $this->event('user', $user->id, ReliabilityEvent::TYPE_NO_SHOW);
        $this->event('user', $user->id, ReliabilityEvent::TYPE_CANCELACION_TARDIA);
        $this->event('user', $user->id, ReliabilityEvent::TYPE_RESPUESTA_RAPIDA);
        $this->event('user', $user->id, ReliabilityEvent::TYPE_CALIFICACION_POSITIVA);

        $score = $this->service()->refreshForUser($user);

        $expected = $this->service()->computeScore(
            noShows: 1, lateCancellations: 1, fastResponses: 1, positiveRatings: 1, negativeRatings: 0
        );
        $this->assertSame($expected, $score->score);
    }

    // ── 4. Detección automática de no-show por scheduler ────────────────

    public function test_detect_no_shows_registra_eventos_para_ambos_clubs(): void
    {
        $capHome = $this->makeUser();
        $capAway = $this->makeUser();
        $home    = $this->makeClub($capHome);
        $away    = $this->makeClub($capAway);

        // Amistoso que ya pasó sin reporte de nadie.
        $fm = FriendlyMatch::create([
            'home_club_id' => $home->id,
            'away_club_id' => $away->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'scheduled_at' => now()->subHours(2),
        ]);

        $this->artisan('social:detect-no-shows')->assertSuccessful();

        $this->assertDatabaseHas('reliability_events', [
            'subject_type' => 'club', 'subject_id' => $home->id,
            'type' => ReliabilityEvent::TYPE_NO_SHOW, 'friendly_match_id' => $fm->id,
        ]);
        $this->assertDatabaseHas('reliability_events', [
            'subject_type' => 'club', 'subject_id' => $away->id,
            'type' => ReliabilityEvent::TYPE_NO_SHOW, 'friendly_match_id' => $fm->id,
        ]);

        // El amistoso queda cancelado para no detectarse de nuevo.
        $this->assertSame(FriendlyMatch::STATUS_CANCELADO, $fm->fresh()->status);
    }

    // ── 5. No-show futuro no se detecta ─────────────────────────────────

    public function test_amistoso_futuro_no_genera_no_show(): void
    {
        $capHome = $this->makeUser();
        $capAway = $this->makeUser();
        $home    = $this->makeClub($capHome);
        $away    = $this->makeClub($capAway);

        FriendlyMatch::create([
            'home_club_id' => $home->id,
            'away_club_id' => $away->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'scheduled_at' => now()->addDays(2),
        ]);

        $this->artisan('social:detect-no-shows')->assertSuccessful();

        $this->assertSame(0, ReliabilityEvent::where('type', ReliabilityEvent::TYPE_NO_SHOW)->count());
    }

    // ── 6. Pausa automática al segundo no-show en 30 días ───────────────

    public function test_dos_no_shows_en_30_dias_activan_pausa(): void
    {
        $cap  = $this->makeUser();
        $club = $this->makeClub($cap);

        $this->event('club', $club->id, ReliabilityEvent::TYPE_NO_SHOW, now()->subDays(10));
        $this->event('club', $club->id, ReliabilityEvent::TYPE_NO_SHOW, now()->subDays(5));

        $score = $this->service()->refreshForClub($club);

        $this->assertTrue($score->is_paused);
        $this->assertNotNull($score->paused_at);
    }

    // ── 7. No-show fuera de 30d no activa pausa ──────────────────────────

    public function test_no_show_fuera_de_ventana_no_pausa(): void
    {
        $cap  = $this->makeUser();
        $club = $this->makeClub($cap);

        // Un no-show antiguo (fuera de los 30d) y uno reciente.
        $this->event('club', $club->id, ReliabilityEvent::TYPE_NO_SHOW, now()->subDays(45));
        $this->event('club', $club->id, ReliabilityEvent::TYPE_NO_SHOW, now()->subDays(10));

        $score = $this->service()->refreshForClub($club);

        // Solo 1 no-show reciente: no se supera el umbral de 2.
        $this->assertFalse($score->is_paused);
    }

    // ── 8. Reactivación manual desbloquea publicar ───────────────────────

    public function test_reactivacion_manual_desbloquea_publicar(): void
    {
        $cap  = $this->makeUser();
        $club = $this->makeClub($cap);

        // Activar pausa con 2 no-shows.
        $this->event('club', $club->id, ReliabilityEvent::TYPE_NO_SHOW, now()->subDays(5));
        $this->event('club', $club->id, ReliabilityEvent::TYPE_NO_SHOW, now()->subDays(2));
        $score = $this->service()->refreshForClub($club);
        $this->assertTrue($score->is_paused);

        // El capitán va a la pantalla de reactivación.
        $this->actingAs($cap)
            ->get(route('social.oportunidades.reactivar'))
            ->assertOk()
            ->assertSee('pausada');

        // Confirma y se reactiva.
        $this->actingAs($cap)
            ->post(route('social.oportunidades.reactivar.confirmar'), ['acknowledged' => '1'])
            ->assertRedirect(route('social.oportunidades.create'));

        // Ya no está pausado.
        $this->assertFalse($this->service()->isPaused('club', $club->id));
    }

    // ── 9. Sin el acknowledge no se reactiva ────────────────────────────

    public function test_reactivacion_sin_confirmacion_falla(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('social.oportunidades.reactivar.confirmar'), [])
            ->assertSessionHasErrors('acknowledged');
    }

    // ── 10. Detect no-shows es idempotente ──────────────────────────────

    public function test_detect_no_shows_idempotente(): void
    {
        $capHome = $this->makeUser();
        $capAway = $this->makeUser();
        $home    = $this->makeClub($capHome);
        $away    = $this->makeClub($capAway);

        $fm = FriendlyMatch::create([
            'home_club_id' => $home->id,
            'away_club_id' => $away->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'scheduled_at' => now()->subHours(3),
        ]);

        // Primera ejecución: genera 2 eventos (uno por club) y cancela el amistoso.
        $this->artisan('social:detect-no-shows');
        $this->assertSame(2, ReliabilityEvent::where('type', ReliabilityEvent::TYPE_NO_SHOW)->count());

        // Segunda ejecución: el amistoso ya está cancelado, no genera más.
        $this->artisan('social:detect-no-shows');
        $this->assertSame(2, ReliabilityEvent::where('type', ReliabilityEvent::TYPE_NO_SHOW)->count());
    }

    // ── 11. Score para club funciona igual que para usuario ──────────────

    public function test_score_club(): void
    {
        $cap  = $this->makeUser();
        $club = $this->makeClub($cap);

        $this->event('club', $club->id, ReliabilityEvent::TYPE_CANCELACION_TARDIA);
        $this->event('club', $club->id, ReliabilityEvent::TYPE_RESPUESTA_RAPIDA);

        $score = $this->service()->refreshForClub($club);

        $expected = $this->service()->computeScore(
            noShows: 0, lateCancellations: 1, fastResponses: 1, positiveRatings: 0, negativeRatings: 0
        );
        $this->assertSame($expected, $score->score);
        $this->assertFalse($score->is_paused);
    }

    // ── 12. Rebuild procesa todos los sujetos ───────────────────────────

    public function test_rebuild_procesa_todos_los_sujetos(): void
    {
        $userA = $this->makeUser();
        $userB = $this->makeUser();
        $capC  = $this->makeUser();
        $club  = $this->makeClub($capC);

        $this->event('user', $userA->id, ReliabilityEvent::TYPE_NO_SHOW);
        $this->event('user', $userB->id, ReliabilityEvent::TYPE_CALIFICACION_POSITIVA);
        $this->event('club', $club->id,  ReliabilityEvent::TYPE_RESPUESTA_RAPIDA);

        $this->service()->rebuild();

        $this->artisan('social:rebuild-reliability')->assertSuccessful();

        $this->assertDatabaseHas('reliability_scores', ['subject_type' => 'user', 'subject_id' => $userA->id]);
        $this->assertDatabaseHas('reliability_scores', ['subject_type' => 'user', 'subject_id' => $userB->id]);
        $this->assertDatabaseHas('reliability_scores', ['subject_type' => 'club', 'subject_id' => $club->id]);
    }
}
