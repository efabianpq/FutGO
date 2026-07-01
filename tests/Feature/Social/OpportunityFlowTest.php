<?php

namespace Tests\Feature\Social;

use App\Exceptions\Social\OpportunityException;
use App\Models\Social\ContentReport;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Social\ReliabilityEvent;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use App\Services\Social\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Fase 1 · Sesión S1-B: flujo de publicación y respuesta de
 * oportunidades (el corazón funcional del módulo Social).
 *
 * Cubre: publicar cada tipo, validaciones (nivel/usuario registrado/contenido),
 * exploración con filtro de nivel, responder, aceptar (verificando la entidad
 * concreta creada por tipo), transacción de aceptación, cancelar con
 * reliability_event, vencimiento por scheduler y reporte de contenido.
 */
class OpportunityFlowTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'Jugador ' . uniqid(),
            'email'     => uniqid('user') . '@test.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ], $extra));
    }

    private function makeClub(User $captain, array $extra = []): Club
    {
        return Club::create(array_merge([
            'name'               => 'Club ' . uniqid(),
            'slug'               => uniqid('club-'),
            'status'             => 'validado',
            'created_by_user_id' => $captain->id,
            'captain_user_id'    => $captain->id,
        ], $extra));
    }

    private function service(): OpportunityService
    {
        return app(OpportunityService::class);
    }

    // ── 1. Publicar cada tipo ───────────────────────────────────────────

    public function test_capitan_publica_buscar_rival(): void
    {
        $captain = $this->makeUser(['play_level' => 'intermedio']);
        $club    = $this->makeClub($captain, ['play_level' => 'intermedio']);

        $response = $this->actingAs($captain)->post(route('social.oportunidades.store'), [
            'type'             => Opportunity::TYPE_BUSCAR_RIVAL,
            'city'             => 'Asunción',
            'required_level'   => 'intermedio',
            'club_id'          => $club->id,
            'window_start'     => now()->addDays(3)->format('Y-m-d H:i:s'),
            'cancha_propuesta' => 'Polideportivo',
            'descripcion'      => 'Buscamos un amistoso para el finde.',
        ]);

        $op = Opportunity::first();
        $this->assertNotNull($op);
        $response->assertRedirect(route('social.oportunidades.show', $op));
        $this->assertSame(Opportunity::TYPE_BUSCAR_RIVAL, $op->type);
        $this->assertSame($club->id, $op->club_id);
        $this->assertSame('Polideportivo', $op->payload['cancha_propuesta']);
        $this->assertTrue($op->isAbierta());
    }

    public function test_capitan_publica_buscar_jugador(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);

        $this->actingAs($captain)->post(route('social.oportunidades.store'), [
            'type'           => Opportunity::TYPE_BUSCAR_JUGADOR,
            'city'           => 'Luque',
            'required_level' => 'recreativo',
            'club_id'        => $club->id,
            'posiciones'     => 'Arquero',
            'cupos'          => 2,
        ])->assertSessionHasNoErrors();

        $op = Opportunity::first();
        $this->assertSame(Opportunity::TYPE_BUSCAR_JUGADOR, $op->type);
        $this->assertSame(2, $op->payload['cupos']);
    }

    public function test_capitan_publica_buscar_refuerzo(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);

        $this->actingAs($captain)->post(route('social.oportunidades.store'), [
            'type'           => Opportunity::TYPE_BUSCAR_REFUERZO,
            'city'           => 'San Lorenzo',
            'required_level' => 'competitivo',
            'club_id'        => $club->id,
            'partido'        => 'vs Tigres, sábado 19:00',
            'posicion'       => 'Delantero',
        ])->assertSessionHasNoErrors();

        $op = Opportunity::first();
        $this->assertSame(Opportunity::TYPE_BUSCAR_REFUERZO, $op->type);
        $this->assertSame('vs Tigres, sábado 19:00', $op->payload['partido']);
    }

    public function test_jugador_publica_buscar_equipo(): void
    {
        $player = $this->makeUser(['play_level' => 'recreativo']);

        $this->actingAs($player)->post(route('social.oportunidades.store'), [
            'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
            'city'           => 'Asunción',
            'required_level' => 'recreativo',
            'posicion'       => 'Volante',
            'disponibilidad' => 'Fines de semana',
        ])->assertSessionHasNoErrors();

        $op = Opportunity::first();
        $this->assertSame(Opportunity::TYPE_BUSCAR_EQUIPO, $op->type);
        $this->assertSame($player->id, $op->user_id);
        $this->assertNull($op->club_id);
        $this->assertSame('Volante', $op->payload['posicion']);
    }

    // ── 2. Validaciones ─────────────────────────────────────────────────

    public function test_publicar_requiere_nivel(): void
    {
        $player = $this->makeUser();

        $this->actingAs($player)
            ->from(route('social.oportunidades.create'))
            ->post(route('social.oportunidades.store'), [
                'type'     => Opportunity::TYPE_BUSCAR_EQUIPO,
                'city'     => 'Asunción',
                'posicion' => 'Volante',
            ])
            ->assertSessionHasErrors('required_level');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_invitado_sin_cuenta_no_puede_publicar(): void
    {
        $this->post(route('social.oportunidades.store'), [
            'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
            'city'           => 'Asunción',
            'required_level' => 'recreativo',
            'posicion'       => 'Volante',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_publicar_como_club_ajeno_falla(): void
    {
        $captain = $this->makeUser();
        $ajeno   = $this->makeUser();
        $club    = $this->makeClub($captain);   // capitaneado por $captain, no por $ajeno

        $this->actingAs($ajeno)
            ->from(route('social.oportunidades.create'))
            ->post(route('social.oportunidades.store'), [
                'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
                'city'           => 'Asunción',
                'required_level' => 'intermedio',
                'club_id'        => $club->id,
                'window_start'   => now()->addDay()->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('opportunities', 0);
    }

    public function test_filtro_de_contenido_rechaza_palabra_prohibida(): void
    {
        $player = $this->makeUser();

        $this->actingAs($player)
            ->from(route('social.oportunidades.create'))
            ->post(route('social.oportunidades.store'), [
                'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
                'city'           => 'Asunción',
                'required_level' => 'recreativo',
                'posicion'       => 'Volante',
                'descripcion'    => 'Sos un idiota si no me llamás',
            ])
            ->assertSessionHasErrors('descripcion');

        $this->assertDatabaseCount('opportunities', 0);
    }

    // ── 3. Exploración + filtro de nivel ────────────────────────────────

    public function test_listado_publico_accesible_sin_login(): void
    {
        $this->get(route('social.oportunidades.index'))->assertOk();
    }

    public function test_listado_filtra_por_nivel_del_que_mira(): void
    {
        $recreCaptain = $this->makeUser();
        $compeCaptain = $this->makeUser();
        $recreClub = $this->makeClub($recreCaptain, ['name' => 'EquipoRecreativo', 'play_level' => 'recreativo']);
        $compeClub = $this->makeClub($compeCaptain, ['name' => 'EquipoCompetitivo', 'play_level' => 'competitivo']);

        Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $recreClub->id, 'user_id' => $recreCaptain->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
        ]);
        Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $compeClub->id, 'user_id' => $compeCaptain->id,
            'city' => 'Asunción', 'required_level' => 'competitivo', 'status' => Opportunity::STATUS_ABIERTA,
        ]);

        $viewer = $this->makeUser(['play_level' => 'recreativo']);

        // Por defecto (sin parámetro) ve todas las oportunidades sin filtro de nivel.
        $this->actingAs($viewer)->get(route('social.oportunidades.index'))
            ->assertSee('EquipoRecreativo')
            ->assertSee('EquipoCompetitivo');

        // Con "mio" solo ve su nivel (recreativo).
        $this->actingAs($viewer)->get(route('social.oportunidades.index', ['nivel' => 'mio']))
            ->assertSee('EquipoRecreativo')
            ->assertDontSee('EquipoCompetitivo');

        // Forzando "todos los niveles" también ve ambas.
        $this->actingAs($viewer)->get(route('social.oportunidades.index', ['nivel' => 'todos']))
            ->assertSee('EquipoRecreativo')
            ->assertSee('EquipoCompetitivo');
    }

    // ── 4. Responder ────────────────────────────────────────────────────

    public function test_responder_a_oportunidad(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $op      = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_JUGADOR, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
            'payload' => ['cupos' => 1],
        ]);

        $player = $this->makeUser();
        $this->actingAs($player)->post(route('social.oportunidades.respond', $op), [
            'message' => 'Disponible, juego de arquero.',
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('opportunity_responses', [
            'opportunity_id' => $op->id, 'user_id' => $player->id, 'status' => OpportunityResponse::STATUS_PENDIENTE,
        ]);
    }

    public function test_no_se_puede_responder_la_propia_oportunidad(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $op      = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_JUGADOR, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
        ]);

        $this->actingAs($captain)
            ->from(route('social.oportunidades.show', $op))
            ->post(route('social.oportunidades.respond', $op), ['message' => 'me respondo'])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('opportunity_responses', 0);
    }

    // ── 5. Aceptar: cada tipo crea la entidad correcta ──────────────────

    public function test_aceptar_rival_crea_friendly_match(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap);
        $away    = $this->makeClub($awayCap);

        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $home->id, 'user_id' => $homeCap->id,
            'city' => 'Asunción', 'required_level' => 'intermedio', 'status' => Opportunity::STATUS_ABIERTA,
            'window_start' => now()->addDays(3), 'payload' => ['cancha_propuesta' => 'La Bombonera'],
        ]);
        $resp = $op->responses()->create([
            'user_id' => $awayCap->id, 'club_id' => $away->id, 'status' => OpportunityResponse::STATUS_PENDIENTE,
        ]);

        $this->actingAs($homeCap)->post(route('social.oportunidades.responses.accept', $resp))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('friendly_matches', [
            'opportunity_id' => $op->id, 'home_club_id' => $home->id, 'away_club_id' => $away->id,
            'status' => FriendlyMatch::STATUS_CONFIRMADO, 'location' => 'La Bombonera',
        ]);
        $this->assertTrue($op->fresh()->isCerrada());
        $this->assertTrue($resp->fresh()->isAceptada());
    }

    public function test_aceptar_jugador_agrega_a_club_players_y_descuenta_cupo(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_JUGADOR, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
            'payload' => ['cupos' => 1, 'posicion' => 'Arquero'],
        ]);

        $player = $this->makeUser();
        $resp   = $op->responses()->create(['user_id' => $player->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);

        $this->actingAs($captain)->post(route('social.oportunidades.responses.accept', $resp));

        $this->assertDatabaseHas('club_players', [
            'club_id' => $club->id, 'user_id' => $player->id, 'status' => 'active',
        ]);
        // Cupo agotado → oportunidad cerrada.
        $this->assertTrue($op->fresh()->isCerrada());
        $this->assertSame(0, $op->fresh()->payload['cupos']);
    }

    public function test_aceptar_jugador_con_cupos_restantes_sigue_abierta(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_JUGADOR, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
            'payload' => ['cupos' => 2],
        ]);
        $resp = $op->responses()->create(['user_id' => $this->makeUser()->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);

        $this->actingAs($captain)->post(route('social.oportunidades.responses.accept', $resp));

        $this->assertTrue($op->fresh()->isAbierta());
        $this->assertSame(1, $op->fresh()->payload['cupos']);
    }

    public function test_aceptar_refuerzo_registra_asignacion_sin_tocar_plantilla(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_REFUERZO, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'competitivo', 'status' => Opportunity::STATUS_ABIERTA,
            'payload' => ['partido' => 'vs Tigres'],
        ]);
        $player = $this->makeUser();
        $resp   = $op->responses()->create(['user_id' => $player->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);

        $this->actingAs($captain)->post(route('social.oportunidades.responses.accept', $resp));

        $fresh = $op->fresh();
        $this->assertTrue($fresh->isCerrada());
        $this->assertSame($player->id, $fresh->payload['assignment']['user_id']);
        // No toca la plantilla permanente.
        $this->assertDatabaseMissing('club_players', ['club_id' => $club->id, 'user_id' => $player->id]);
        $this->assertSame(0, FriendlyMatch::count());
    }

    public function test_aceptar_equipo_agrega_jugador_al_club_respondente(): void
    {
        $player  = $this->makeUser(['play_level' => 'recreativo']);
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_EQUIPO, 'user_id' => $player->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
            'payload' => ['posicion' => 'Volante'],
        ]);

        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);
        $resp    = $op->responses()->create(['user_id' => $captain->id, 'club_id' => $club->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);

        // El dueño es el jugador publicante: él acepta la convocatoria del club.
        $this->actingAs($player)->post(route('social.oportunidades.responses.accept', $resp));

        $this->assertDatabaseHas('club_players', ['club_id' => $club->id, 'user_id' => $player->id]);
        $this->assertTrue($op->fresh()->isCerrada());
    }

    public function test_aceptacion_es_transaccional_si_falla_no_cierra_la_oportunidad(): void
    {
        $homeCap = $this->makeUser();
        $home    = $this->makeClub($homeCap);
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $home->id, 'user_id' => $homeCap->id,
            'city' => 'Asunción', 'required_level' => 'intermedio', 'status' => Opportunity::STATUS_ABIERTA,
            'window_start' => now()->addDays(2),
        ]);
        // Respuesta SIN club rival → acceptRival debe fallar y revertir todo.
        $resp = $op->responses()->create(['user_id' => $this->makeUser()->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);

        try {
            $this->service()->accept($resp);
            $this->fail('Se esperaba una OpportunityException.');
        } catch (OpportunityException $e) {
            // esperado
        }

        $this->assertTrue($op->fresh()->isAbierta());           // no se cerró
        $this->assertSame(0, FriendlyMatch::count());            // no se creó la entidad
        $this->assertTrue($resp->fresh()->isPendiente());        // la respuesta sigue pendiente
    }

    // ── 6. Cancelar con reliability_event ───────────────────────────────

    public function test_cancelar_dentro_de_24h_genera_reliability_event(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap);
        $away    = $this->makeClub($awayCap);

        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $home->id, 'user_id' => $homeCap->id,
            'city' => 'Asunción', 'required_level' => 'intermedio', 'status' => Opportunity::STATUS_ABIERTA,
            'window_start' => now()->addHours(12),
        ]);
        $resp = $op->responses()->create(['user_id' => $awayCap->id, 'club_id' => $away->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);

        $this->service()->accept($resp);                        // crea el amistoso, cierra la op
        $fm = FriendlyMatch::first();

        $this->actingAs($homeCap)
            ->from(route('social.oportunidades.show', $op))
            ->post(route('social.oportunidades.cancel', $op), ['reason' => 'Se nos lesionaron jugadores'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('reliability_events', [
            'subject_type' => 'club', 'subject_id' => $home->id,
            'type' => ReliabilityEvent::TYPE_CANCELACION_TARDIA, 'friendly_match_id' => $fm->id,
        ]);
        $this->assertTrue($op->fresh()->isCancelada());
        $this->assertTrue($fm->fresh()->isCancelado());
    }

    public function test_cancelar_fuera_de_24h_no_genera_reliability_event(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap);
        $away    = $this->makeClub($awayCap);

        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $home->id, 'user_id' => $homeCap->id,
            'city' => 'Asunción', 'required_level' => 'intermedio', 'status' => Opportunity::STATUS_ABIERTA,
            'window_start' => now()->addDays(5),
        ]);
        $resp = $op->responses()->create(['user_id' => $awayCap->id, 'club_id' => $away->id, 'status' => OpportunityResponse::STATUS_PENDIENTE]);
        $this->service()->accept($resp);

        $this->actingAs($homeCap)
            ->from(route('social.oportunidades.show', $op))
            ->post(route('social.oportunidades.cancel', $op), ['reason' => 'Reprogramamos']);

        $this->assertSame(0, ReliabilityEvent::where('type', ReliabilityEvent::TYPE_CANCELACION_TARDIA)->count());
        $this->assertTrue($op->fresh()->isCancelada());
    }

    // ── 7. Vencimiento por scheduler ────────────────────────────────────

    public function test_scheduler_vence_oportunidades_pasadas(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain);

        $vieja = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'intermedio', 'status' => Opportunity::STATUS_ABIERTA,
            'expires_at' => now()->subDay(),
        ]);
        $vigente = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'club_id' => $club->id, 'user_id' => $captain->id,
            'city' => 'Asunción', 'required_level' => 'intermedio', 'status' => Opportunity::STATUS_ABIERTA,
            'expires_at' => now()->addDay(),
        ]);

        $this->artisan('social:expire-opportunities')->assertSuccessful();

        $this->assertTrue($vieja->fresh()->isVencida());
        $this->assertTrue($vigente->fresh()->isAbierta());

        // La vencida no aparece en el listado activo.
        $this->get(route('social.oportunidades.index', ['nivel' => 'todos']))->assertOk();
        $this->assertSame(1, Opportunity::active()->count());
    }

    // ── 8. Reporte de contenido ─────────────────────────────────────────

    public function test_reportar_oportunidad_crea_content_report(): void
    {
        $author = $this->makeUser();
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_EQUIPO, 'user_id' => $author->id,
            'city' => 'Asunción', 'required_level' => 'recreativo', 'status' => Opportunity::STATUS_ABIERTA,
        ]);

        $reporter = $this->makeUser();
        $this->actingAs($reporter)
            ->from(route('social.oportunidades.show', $op))
            ->post(route('social.oportunidades.report', $op), [
                'reason'  => 'spam',
                'details' => 'Publicación repetida muchas veces.',
            ])->assertSessionHas('status');

        $this->assertDatabaseHas('content_reports', [
            'reporter_user_id' => $reporter->id, 'reportable_type' => 'opportunity',
            'reportable_id' => $op->id, 'reason' => 'spam', 'status' => ContentReport::STATUS_PENDIENTE,
        ]);
    }
}
