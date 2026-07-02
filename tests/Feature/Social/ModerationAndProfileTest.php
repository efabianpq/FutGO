<?php

namespace Tests\Feature\Social;

use App\Exceptions\Social\OpportunityException;
use App\Models\Social\ContentReport;
use App\Models\Social\Opportunity;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use App\Services\Social\ModerationService;
use App\Services\Social\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Sesión S1-F: Moderación y perfil público de jugador.
 *
 * Cubre: ficha pública accesible sin login, ficha no expone datos sensibles,
 * reporte llega al panel admin, ocultamiento saca la oportunidad del listado,
 * suspensión impide publicar y responder.
 */
class ModerationAndProfileTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'Jugador ' . uniqid(),
            'email'     => uniqid('user') . '@test.com',
            'password'  => bcrypt('password'),
            'role'      => 'user',
        ], $extra));
    }

    private function makeAdmin(): User
    {
        return $this->makeUser(['role' => 'admin']);
    }

    private function makeClub(User $captain): Club
    {
        return Club::create([
            'name'               => 'Club ' . uniqid(),
            'slug'               => uniqid('club-'),
            'status'             => 'validado',
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
        ]);
    }

    private function makeOpportunity(User $user, array $extra = []): Opportunity
    {
        $club = $this->makeClub($user);
        ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => $user->id,
            'is_captain'          => true,
            'verification_status' => 'registrado',
            'status'              => 'active',
        ]);

        return Opportunity::create(array_merge([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $user->id,
            'club_id'        => $club->id,
            'city'           => 'Rosario',
            'required_level' => 'intermedio',
            'status'         => Opportunity::STATUS_ABIERTA,
            'payload'        => [],
        ], $extra));
    }

    // ── 1. Ficha pública de jugador accesible sin login ─────────────────

    /** @test */
    public function ficha_publica_accesible_sin_login(): void
    {
        $player = $this->makeUser(['city' => 'Rosario', 'play_level' => 'intermedio']);

        $response = $this->get(route('social.player.show', $player->futgo_id));

        $response->assertOk();
        $response->assertSee($player->name);
    }

    /** @test */
    public function ficha_publica_muestra_nivel_y_ciudad(): void
    {
        $player = $this->makeUser(['city' => 'Buenos Aires', 'play_level' => 'competitivo']);

        $response = $this->get(route('social.player.show', $player->futgo_id));

        $response->assertOk();
        $response->assertSee('Buenos Aires');
        $response->assertSee('Competitivo');
    }

    /** @test */
    public function ficha_publica_no_expone_email_telefono_documento(): void
    {
        $player = $this->makeUser([
            'email'           => 'privado@example.com',
            'phone_whatsapp'  => '+5493416000000',
            'document'        => '30123456',
        ]);

        $response = $this->get(route('social.player.show', $player->futgo_id));

        $response->assertOk();
        $response->assertDontSee('privado@example.com');
        $response->assertDontSee('+5493416000000');
        $response->assertDontSee('30123456');
    }

    /** @test */
    public function ficha_publica_devuelve_404_para_futgo_id_inexistente(): void
    {
        $response = $this->get(route('social.player.show', 'FG-ZZZZZZ'));

        $response->assertNotFound();
    }

    // ── 2. Score de confiabilidad en ficha pública ──────────────────────

    /** @test */
    public function score_bajo_no_aparece_en_ficha_publica(): void
    {
        $player = $this->makeUser();
        ReliabilityScore::create([
            'subject_type' => 'user',
            'subject_id'   => $player->id,
            'score'        => 60,
            'is_paused'    => false,
        ]);

        $response = $this->get(route('social.player.show', $player->futgo_id));

        $response->assertOk();
        $response->assertDontSee('Score de confiabilidad');
    }

    /** @test */
    public function score_alto_si_aparece_en_ficha_publica(): void
    {
        $player = $this->makeUser();
        ReliabilityScore::create([
            'subject_type' => 'user',
            'subject_id'   => $player->id,
            'score'        => 85,
            'is_paused'    => false,
        ]);

        $response = $this->get(route('social.player.show', $player->futgo_id));

        $response->assertOk();
        $response->assertSee('85');
        $response->assertSee('Score de confiabilidad');
    }

    // ── 3. Panel admin de moderación ────────────────────────────────────

    /** @test */
    public function reporte_aparece_en_panel_admin(): void
    {
        $admin    = $this->makeAdmin();
        $reporter = $this->makeUser();
        $reported = $this->makeUser();

        ContentReport::create([
            'reporter_user_id' => $reporter->id,
            'reportable_type'  => 'user',
            'reportable_id'    => $reported->id,
            'reason'           => 'spam',
            'status'           => 'pendiente',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.social.moderacion.index'));

        $response->assertOk();
        $response->assertSee('spam');
        $response->assertSee($reporter->name);
    }

    /** @test */
    public function admin_puede_desestimar_reporte(): void
    {
        $admin    = $this->makeAdmin();
        $reporter = $this->makeUser();
        $reported = $this->makeUser();

        $report = ContentReport::create([
            'reporter_user_id' => $reporter->id,
            'reportable_type'  => 'user',
            'reportable_id'    => $reported->id,
            'reason'           => 'spam',
            'status'           => 'pendiente',
        ]);

        $this->actingAs($admin)->post(route('admin.social.moderacion.resolve', $report), [
            'action'      => 'dismissed',
            'admin_notes' => 'Sin evidencia',
        ])->assertRedirect();

        $report->refresh();
        $this->assertEquals('resuelto', $report->status);
        $this->assertEquals('dismissed', $report->resolution_action);
        $this->assertEquals($admin->id, $report->reviewed_by_user_id);
    }

    // ── 4. Ocultamiento de contenido ────────────────────────────────────

    /** @test */
    public function oportunidad_oculta_no_aparece_en_listado_publico(): void
    {
        $user = $this->makeUser();
        $op   = $this->makeOpportunity($user);

        // Ocultar la oportunidad via ModerationService.
        $service = app(ModerationService::class);
        $service->hideEntity($op);

        $response = $this->get(route('social.oportunidades.index'));

        $response->assertOk();
        // La oportunidad está oculta, no debe aparecer en el listado.
        $this->assertDatabaseHas('opportunities', ['id' => $op->id, 'is_hidden' => true]);

        // El query de index usa scope visible(): verificar via DB que se filtra.
        $visible = Opportunity::active()->visible()->get();
        $this->assertFalse($visible->contains('id', $op->id));
    }

    /** @test */
    public function oportunidad_oculta_devuelve_404_en_show(): void
    {
        $user = $this->makeUser();
        $op   = $this->makeOpportunity($user, ['is_hidden' => true]);

        $response = $this->get(route('social.oportunidades.show', $op));

        $response->assertNotFound();
    }

    /** @test */
    public function oportunidad_visible_accesible_normalmente(): void
    {
        $user = $this->makeUser();
        $op   = $this->makeOpportunity($user);

        $response = $this->get(route('social.oportunidades.show', $op));

        $response->assertOk();
    }

    // ── 5. Suspensión de usuario ────────────────────────────────────────

    /** @test */
    public function usuario_suspendido_no_puede_publicar_oportunidad(): void
    {
        $user = $this->makeUser(['is_suspended' => true, 'suspended_until' => now()->addDays(7)]);
        $club = $this->makeClub($user);
        ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => $user->id,
            'is_captain'          => true,
            'verification_status' => 'registrado',
            'status'              => 'active',
        ]);

        $service = app(OpportunityService::class);

        $this->expectException(OpportunityException::class);
        $service->publish($user, [
            'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
            'city'           => 'Córdoba',
            'required_level' => 'recreativo',
            'payload'        => [],
        ]);
    }

    /** @test */
    public function usuario_suspendido_no_puede_responder_oportunidad(): void
    {
        $owner     = $this->makeUser(['play_level' => 'recreativo']);
        $suspended = $this->makeUser(['is_suspended' => true, 'suspended_until' => now()->addDays(3)]);

        $op = $this->makeOpportunity($owner);

        $service = app(OpportunityService::class);

        $this->expectException(OpportunityException::class);
        $service->respond($op, $suspended, ['message' => 'Hola']);
    }

    /** @test */
    public function suspension_vencida_no_bloquea(): void
    {
        $user = $this->makeUser([
            'is_suspended'    => true,
            'suspended_until' => now()->subDay(),  // ya venció
        ]);

        $this->assertFalse($user->isSuspended());
    }

    /** @test */
    public function suspension_indefinida_bloquea(): void
    {
        $user = $this->makeUser([
            'is_suspended'    => true,
            'suspended_until' => null,
        ]);

        $this->assertTrue($user->isSuspended());
    }

    /** @test */
    public function admin_puede_suspender_usuario_via_reporte(): void
    {
        $admin    = $this->makeAdmin();
        $reporter = $this->makeUser();
        $reported = $this->makeUser();

        $op = $this->makeOpportunity($reported);

        $report = ContentReport::create([
            'reporter_user_id' => $reporter->id,
            'reportable_type'  => 'opportunity',
            'reportable_id'    => $op->id,
            'reason'           => 'estafa',
            'status'           => 'pendiente',
        ]);

        $service = app(ModerationService::class);
        $service->resolveReport($report, $admin, 'suspended', 'Actividad sospechosa', 7, 'estafa confirmada');

        $reported->refresh();
        $this->assertTrue($reported->isSuspended());

        $report->refresh();
        $this->assertEquals('resuelto', $report->status);
        $this->assertEquals('suspended', $report->resolution_action);
        $this->assertEquals($admin->id, $report->reviewed_by_user_id);
    }
}
