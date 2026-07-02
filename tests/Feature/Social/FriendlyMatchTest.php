<?php

namespace Tests\Feature\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\ReliabilityEvent;
use App\Models\Torneos\Club;
use App\Models\User;
use App\Services\Social\FriendlyMatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Fase 1 · Sesión S1-C: ciclo de vida del amistoso y doble
 * confirmación de resultado.
 *
 * Cubre: confirmación cuando coinciden, disputa cuando difieren, rectificación
 * que resuelve la disputa, escalamiento + resolución por admin, cancelación
 * tardía/anticipada, integración a Mi Carrera y al perfil del club, y que un
 * amistoso cancelado sale del historial activo pero queda para el admin.
 */
class FriendlyMatchTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'Cap ' . uniqid(),
            'email'     => uniqid('user') . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'user',
        ], $extra));
    }

    private function makeClub(User $captain, string $name): Club
    {
        return Club::create([
            'name'               => $name,
            'slug'               => uniqid('club-'),
            'status'             => 'validado',
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
        ]);
    }

    private function makeFriendly(Club $home, Club $away, array $extra = []): FriendlyMatch
    {
        return FriendlyMatch::create(array_merge([
            'home_club_id' => $home->id,
            'away_club_id' => $away->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'scheduled_at' => now()->addDays(3),
        ], $extra));
    }

    private function service(): FriendlyMatchService
    {
        return app(FriendlyMatchService::class);
    }

    // ── 1. Confirmación cuando coinciden ────────────────────────────────

    public function test_doble_confirmacion_coincide_queda_jugado(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Visita FC');
        $fm      = $this->makeFriendly($home, $away);

        // El local carga 3-1: todavía pendiente (falta el rival).
        $this->actingAs($homeCap)->from(route('social.amistosos.index'))
            ->post(route('social.amistosos.report', $fm), ['home_score' => 3, 'away_score' => 1]);
        $this->assertTrue($fm->fresh()->isConfirmado());

        // El visitante carga el mismo 3-1: queda jugado y acordado.
        $this->actingAs($awayCap)->post(route('social.amistosos.report', $fm), ['home_score' => 3, 'away_score' => 1]);

        $fm->refresh();
        $this->assertTrue($fm->isJugado());
        $this->assertSame(FriendlyMatch::AGREEMENT_ACORDADO, $fm->result_agreement);
        $this->assertSame(3, $fm->final_home_score);
        $this->assertSame(1, $fm->final_away_score);

        // Atómico: queda inmediatamente en el historial (derivado read-time).
        $this->assertCount(1, $this->service()->clubFriendlies($home));
    }

    // ── 2. Disputa cuando difieren ──────────────────────────────────────

    public function test_marcadores_distintos_queda_en_disputa(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Visita FC');
        $fm      = $this->makeFriendly($home, $away);

        $this->actingAs($homeCap)->post(route('social.amistosos.report', $fm), ['home_score' => 3, 'away_score' => 1]);
        $this->actingAs($awayCap)->post(route('social.amistosos.report', $fm), ['home_score' => 2, 'away_score' => 2]);

        $fm->refresh();
        $this->assertTrue($fm->estaEnDisputa());
        $this->assertNull($fm->final_home_score);
        // No entra al historial mientras está en disputa.
        $this->assertCount(0, $this->service()->clubFriendlies($home));

        // La "notificación en plataforma": la vista muestra el desacuerdo.
        $this->actingAs($homeCap)->get(route('social.amistosos.index'))->assertSee('En disputa');
    }

    // ── 3. Rectificación resuelve la disputa ────────────────────────────

    public function test_rectificacion_resuelve_disputa(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Visita FC');
        $fm      = $this->makeFriendly($home, $away);

        $this->actingAs($homeCap)->post(route('social.amistosos.report', $fm), ['home_score' => 3, 'away_score' => 1]);
        $this->actingAs($awayCap)->post(route('social.amistosos.report', $fm), ['home_score' => 2, 'away_score' => 2]);
        $this->assertTrue($fm->fresh()->estaEnDisputa());

        // El local rectifica a 2-2 → coincide → jugado.
        $this->actingAs($homeCap)->post(route('social.amistosos.report', $fm), ['home_score' => 2, 'away_score' => 2]);

        $fm->refresh();
        $this->assertTrue($fm->isJugado());
        $this->assertSame(2, $fm->final_home_score);
        $this->assertSame(2, $fm->final_away_score);
    }

    // ── 4. Solo el capitán participante puede cargar ────────────────────

    public function test_solo_capitan_participante_puede_cargar_resultado(): void
    {
        $homeCap   = $this->makeUser();
        $awayCap   = $this->makeUser();
        $intruso   = $this->makeUser();
        $home      = $this->makeClub($homeCap, 'Local FC');
        $away      = $this->makeClub($awayCap, 'Visita FC');
        $this->makeClub($intruso, 'Ajeno FC');     // capitán de otro club, no participa
        $fm        = $this->makeFriendly($home, $away);

        $this->actingAs($intruso)->post(route('social.amistosos.report', $fm), ['home_score' => 1, 'away_score' => 0])
            ->assertForbidden();

        $this->assertTrue($fm->fresh()->isConfirmado());
        $this->assertFalse($fm->fresh()->homeHasReported());
    }

    // ── 5. Escalar y resolver por admin ─────────────────────────────────

    public function test_escalar_disputa_y_resolver_por_admin(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $admin   = $this->makeUser(['role' => 'admin']);
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Visita FC');
        $fm      = $this->makeFriendly($home, $away);

        $this->actingAs($homeCap)->post(route('social.amistosos.report', $fm), ['home_score' => 3, 'away_score' => 1]);
        $this->actingAs($awayCap)->post(route('social.amistosos.report', $fm), ['home_score' => 2, 'away_score' => 2]);

        // El visitante escala.
        $this->actingAs($awayCap)->post(route('social.amistosos.escalate', $fm))->assertSessionHas('status');
        $fm->refresh();
        $this->assertTrue($fm->isEscalada());
        $this->assertSame($away->id, $fm->escalated_by_club_id);

        // El admin fija el resultado oficial.
        $this->actingAs($admin)->post(route('admin.amistosos.resolve', $fm), ['home_score' => 3, 'away_score' => 2])
            ->assertSessionHas('status');

        $fm->refresh();
        $this->assertTrue($fm->isJugado());
        $this->assertSame(3, $fm->final_home_score);
        $this->assertSame(2, $fm->final_away_score);
        $this->assertSame($admin->id, $fm->resolved_by_user_id);
        $this->assertTrue($fm->fueResueltoPorAdmin());
    }

    // ── 6. Cancelación tardía / anticipada ──────────────────────────────

    public function test_cancelacion_tardia_genera_reliability_event(): void
    {
        $homeCap = $this->makeUser();
        $away    = $this->makeClub($this->makeUser(), 'Visita FC');
        $home    = $this->makeClub($homeCap, 'Local FC');
        $fm      = $this->makeFriendly($home, $away, ['scheduled_at' => now()->addHours(12)]);

        $this->actingAs($homeCap)->post(route('social.amistosos.cancel', $fm), ['reason' => 'Lesiones'])
            ->assertSessionHas('status');

        $fm->refresh();
        $this->assertTrue($fm->isCancelado());
        $this->assertSame($home->id, $fm->cancelled_by_club_id);
        $this->assertDatabaseHas('reliability_events', [
            'subject_type' => 'club', 'subject_id' => $home->id,
            'type' => ReliabilityEvent::TYPE_CANCELACION_TARDIA, 'friendly_match_id' => $fm->id,
        ]);
    }

    public function test_cancelacion_anticipada_no_penaliza(): void
    {
        $homeCap = $this->makeUser();
        $away    = $this->makeClub($this->makeUser(), 'Visita FC');
        $home    = $this->makeClub($homeCap, 'Local FC');
        $fm      = $this->makeFriendly($home, $away, ['scheduled_at' => now()->addDays(5)]);

        $this->actingAs($homeCap)->post(route('social.amistosos.cancel', $fm), ['reason' => 'Reprogramamos']);

        $fm->refresh();
        $this->assertTrue($fm->isCancelado());
        $this->assertSame(0, ReliabilityEvent::where('type', ReliabilityEvent::TYPE_CANCELACION_TARDIA)->count());
    }

    // ── 7. Integración a Mi Carrera ─────────────────────────────────────

    public function test_amistoso_jugado_aparece_en_mi_carrera(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Rival Carrera FC');
        $fm      = $this->makeFriendly($home, $away);

        $this->service()->reportResult($fm, $home, 4, 0);
        $this->service()->reportResult($fm, $away, 4, 0);
        $this->assertTrue($fm->fresh()->isJugado());

        $this->actingAs($homeCap)->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee('Rival Carrera FC')
            ->assertSee('Amistosos');
    }

    // ── 8. Cancelado fuera del historial activo, visible para el admin ──

    public function test_cancelado_fuera_de_historial_activo_pero_visible_para_admin(): void
    {
        $homeCap = $this->makeUser();
        $admin   = $this->makeUser(['role' => 'admin']);
        $away    = $this->makeClub($this->makeUser(), 'Visita FC');
        $home    = $this->makeClub($homeCap, 'Local FC');
        $fm      = $this->makeFriendly($home, $away, ['scheduled_at' => now()->addDays(2)]);

        $this->service()->cancel($fm, $home, 'No tenemos equipo');

        // No aparece en el historial activo del club.
        $this->assertCount(0, $this->service()->clubFriendlies($home));
        // Sí aparece en la bandeja del admin.
        $this->assertCount(1, $this->service()->cancellations());
        $this->actingAs($admin)->get(route('admin.amistosos.index'))
            ->assertOk()
            ->assertSee('No tenemos equipo');
    }

    // ── 9. El perfil del club muestra el historial de amistosos ─────────

    public function test_perfil_del_club_muestra_amistosos(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Rival Perfil FC');
        $fm      = $this->makeFriendly($home, $away);

        $this->service()->reportResult($fm, $home, 1, 0);
        $this->service()->reportResult($fm, $away, 1, 0);

        $this->actingAs($homeCap)->get(route('torneos.clubes.show', $home))
            ->assertOk()
            ->assertSee('Rival Perfil FC')
            ->assertSee('Historial de amistosos');
    }
}
