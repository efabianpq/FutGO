<?php

namespace Tests\Feature\Social;

use App\Models\Social\ContentReport;
use App\Models\Social\Conversation;
use App\Models\Social\ConversationParticipant;
use App\Models\Social\Follow;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Message;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Social\ReliabilityEvent;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Fase 1 · modelo de datos base.
 * Cubre creación de cada entidad, relaciones y helpers de estado.
 */
class SocialModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'Jugador Test',
            'email'     => uniqid('user') . '@test.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ], $extra));
    }

    private function makeClub(string $name = 'Club Test', array $extra = []): Club
    {
        return Club::create(array_merge([
            'name'   => $name,
            'slug'   => uniqid('club-'),
            'status' => 'validado',
        ], $extra));
    }

    private function makeTournament(User $creator): Tournament
    {
        return Tournament::create([
            'name'                 => 'Copa Test',
            'slug'                 => uniqid('copa-'),
            'sport'                => 'futbol',
            'status'               => 'draft',
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            'third_place_match'    => false,
            'created_by_user_id'   => $creator->id,
        ]);
    }

    // ── 1. Niveles de juego ─────────────────────────────────────────────

    public function test_user_declara_nivel_de_juego(): void
    {
        $sinNivel = $this->makeUser();
        $this->assertFalse($sinNivel->hasPlayLevel());

        $user = $this->makeUser(['play_level' => 'competitivo']);
        $this->assertTrue($user->hasPlayLevel());
        $this->assertTrue($user->isCompetitivo());
        $this->assertFalse($user->isRecreativo());
        $this->assertSame(2, $user->playLevelRank());
    }

    public function test_club_declara_nivel_y_scope_filtra(): void
    {
        $this->makeClub('Recre', ['play_level' => 'recreativo']);
        $elite = $this->makeClub('Elite', ['play_level' => 'elite_amateur']);
        $this->makeClub('SinNivel');

        $this->assertTrue($elite->hasPlayLevel());
        $this->assertTrue($elite->isEliteAmateur());
        $this->assertSame(1, Club::withPlayLevel('elite_amateur')->count());
        $this->assertSame(2, Club::withDeclaredLevel()->count());
    }

    // ── 2. Opportunity ──────────────────────────────────────────────────

    public function test_opportunity_se_crea_con_payload_y_creador(): void
    {
        $club = $this->makeClub();
        $user = $this->makeUser();

        $op = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $user->id,
            'club_id'        => $club->id,
            'city'           => 'Asunción',
            'zone'           => 'Centro',
            'required_level' => 'intermedio',
            'window_start'   => now()->addDay(),
            'window_end'     => now()->addDays(2),
            'status'         => Opportunity::STATUS_ABIERTA,
            'payload'        => ['cancha_propuesta' => 'Polideportivo', 'cupos' => 1],
        ]);

        $this->assertDatabaseHas('opportunities', ['id' => $op->id, 'type' => 'BUSCAR_RIVAL']);
        $this->assertIsArray($op->fresh()->payload);
        $this->assertSame('Polideportivo', $op->fresh()->payload['cancha_propuesta']);
        $this->assertInstanceOf(Club::class, $op->club);
        $this->assertInstanceOf(User::class, $op->user);
    }

    public function test_opportunity_helpers_de_estado_y_tipo(): void
    {
        $op = new Opportunity(['type' => Opportunity::TYPE_BUSCAR_EQUIPO, 'status' => Opportunity::STATUS_ABIERTA]);

        $this->assertTrue($op->isAbierta());
        $this->assertTrue($op->isBuscarEquipo());
        $this->assertFalse($op->isBuscarRival());
        $this->assertTrue($op->canReceiveResponses());

        $op->status = Opportunity::STATUS_CERRADA;
        $this->assertTrue($op->isCerrada());
        $this->assertFalse($op->canReceiveResponses());
    }

    public function test_opportunity_vencida_por_fecha(): void
    {
        $op = new Opportunity([
            'status'     => Opportunity::STATUS_ABIERTA,
            'expires_at' => now()->subHour(),
        ]);

        $this->assertTrue($op->isVencida());
        $this->assertFalse($op->canReceiveResponses());
    }

    // ── 3. OpportunityResponse ──────────────────────────────────────────

    public function test_opportunity_tiene_muchas_respuestas_y_una_aceptada(): void
    {
        $op = Opportunity::create([
            'type'   => Opportunity::TYPE_BUSCAR_JUGADOR,
            'club_id' => $this->makeClub()->id,
            'city'   => 'Asunción',
            'status' => Opportunity::STATUS_ABIERTA,
        ]);

        OpportunityResponse::create([
            'opportunity_id' => $op->id,
            'user_id'        => $this->makeUser()->id,
            'message'        => 'Me interesa',
            'status'         => OpportunityResponse::STATUS_RECHAZADA,
        ]);
        $aceptada = OpportunityResponse::create([
            'opportunity_id' => $op->id,
            'user_id'        => $this->makeUser()->id,
            'message'        => 'Disponible',
            'status'         => OpportunityResponse::STATUS_ACEPTADA,
        ]);

        $this->assertCount(2, $op->responses);
        $this->assertTrue($aceptada->isAceptada());
        $this->assertSame($aceptada->id, $op->acceptedResponse->id);
        $this->assertSame(1, $op->responses()->aceptadas()->count());
    }

    // ── 4. FriendlyMatch + doble confirmación ───────────────────────────

    public function test_friendly_match_se_crea_desde_oportunidad(): void
    {
        $home = $this->makeClub('Local');
        $away = $this->makeClub('Visita');
        $op   = Opportunity::create([
            'type'    => Opportunity::TYPE_BUSCAR_RIVAL,
            'club_id' => $home->id,
            'city'    => 'Asunción',
            'status'  => Opportunity::STATUS_CERRADA,
        ]);

        $fm = FriendlyMatch::create([
            'opportunity_id' => $op->id,
            'home_club_id'   => $home->id,
            'away_club_id'   => $away->id,
            'scheduled_at'   => now()->addDays(3),
            'location'       => 'Cancha del barrio',
            'status'         => FriendlyMatch::STATUS_CONFIRMADO,
        ]);

        $this->assertInstanceOf(Club::class, $fm->homeClub);
        $this->assertInstanceOf(Club::class, $fm->awayClub);
        $this->assertSame($op->id, $fm->opportunity->id);
        $this->assertSame($fm->id, $op->fresh()->friendlyMatch->id);
        $this->assertTrue($fm->isConfirmado());
    }

    public function test_doble_confirmacion_resultado_acordado(): void
    {
        $fm = $this->makeFriendlyMatch();
        $fm->home_reported_home_score = 3;
        $fm->home_reported_away_score = 1;
        $fm->away_reported_home_score = 3;
        $fm->away_reported_away_score = 1;

        $fm->applyAgreementFromReports();

        $this->assertTrue($fm->reportsMatch());
        $this->assertTrue($fm->isJugado());
        $this->assertTrue($fm->hasAgreedResult());
        $this->assertSame(FriendlyMatch::AGREEMENT_ACORDADO, $fm->result_agreement);
        $this->assertSame(3, $fm->final_home_score);
        $this->assertSame(1, $fm->final_away_score);
    }

    public function test_doble_confirmacion_marcadores_distintos_queda_en_disputa(): void
    {
        $fm = $this->makeFriendlyMatch();
        $fm->home_reported_home_score = 3;
        $fm->home_reported_away_score = 1;
        $fm->away_reported_home_score = 2;
        $fm->away_reported_away_score = 2;

        $fm->applyAgreementFromReports();

        $this->assertFalse($fm->reportsMatch());
        $this->assertTrue($fm->estaEnDisputa());
        $this->assertFalse($fm->hasAgreedResult());
        $this->assertNull($fm->final_home_score);
        $this->assertSame(FriendlyMatch::AGREEMENT_EN_DISPUTA, $fm->result_agreement);
    }

    public function test_doble_confirmacion_falta_un_reporte_queda_pendiente(): void
    {
        $fm = $this->makeFriendlyMatch();
        $fm->home_reported_home_score = 3;
        $fm->home_reported_away_score = 1;

        $fm->applyAgreementFromReports();

        $this->assertFalse($fm->bothReported());
        $this->assertSame(FriendlyMatch::AGREEMENT_PENDIENTE, $fm->result_agreement);
        $this->assertTrue($fm->isConfirmado());   // no cambia el estado del partido
    }

    private function makeFriendlyMatch(): FriendlyMatch
    {
        return FriendlyMatch::create([
            'home_club_id' => $this->makeClub('Local')->id,
            'away_club_id' => $this->makeClub('Visita')->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
        ]);
    }

    // ── 5. reliability_events + reliability_scores ──────────────────────

    public function test_reliability_event_polimorfico_sobre_user_y_club(): void
    {
        $user = $this->makeUser();
        $club = $this->makeClub();

        $evUser = $user->reliabilityEvents()->create([
            'type'        => ReliabilityEvent::TYPE_NO_SHOW,
            'occurred_at' => now(),
        ]);
        $club->reliabilityEvents()->create([
            'type'        => ReliabilityEvent::TYPE_RESPUESTA_RAPIDA,
            'occurred_at' => now(),
        ]);

        $this->assertDatabaseHas('reliability_events', ['subject_type' => 'user', 'subject_id' => $user->id]);
        $this->assertDatabaseHas('reliability_events', ['subject_type' => 'club', 'subject_id' => $club->id]);
        $this->assertInstanceOf(User::class, $evUser->subject);
        $this->assertTrue($evUser->isNoShow());
        $this->assertTrue($evUser->isNegative());
        $this->assertCount(1, $user->reliabilityEvents);
    }

    public function test_reliability_event_referencia_oportunidad_y_partido(): void
    {
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'city' => 'Asunción', 'status' => 'abierta',
            'club_id' => $this->makeClub()->id,
        ]);
        $fm = $this->makeFriendlyMatch();

        $ev = $this->makeClub()->reliabilityEvents()->create([
            'type'              => ReliabilityEvent::TYPE_CANCELACION_TARDIA,
            'opportunity_id'    => $op->id,
            'friendly_match_id' => $fm->id,
            'occurred_at'       => now(),
        ]);

        $this->assertSame($op->id, $ev->opportunity->id);
        $this->assertSame($fm->id, $ev->friendlyMatch->id);
    }

    public function test_reliability_event_scopes_noshows_y_withindays(): void
    {
        $user = $this->makeUser();
        $user->reliabilityEvents()->create(['type' => ReliabilityEvent::TYPE_NO_SHOW, 'occurred_at' => now()->subDays(5)]);
        $user->reliabilityEvents()->create(['type' => ReliabilityEvent::TYPE_NO_SHOW, 'occurred_at' => now()->subDays(40)]);
        $user->reliabilityEvents()->create(['type' => ReliabilityEvent::TYPE_RESPUESTA_RAPIDA, 'occurred_at' => now()]);

        $this->assertSame(2, $user->reliabilityEvents()->noShows()->count());
        $this->assertSame(1, $user->reliabilityEvents()->noShows()->withinDays(30)->count());
    }

    public function test_reliability_score_cache_y_pausa(): void
    {
        $club = $this->makeClub();

        $score = $club->reliabilityScore()->create([
            'score'         => 60,
            'no_shows'      => 2,
            'is_paused'     => true,
            'paused_at'     => now(),
            'calculated_at' => now(),
        ]);

        $this->assertInstanceOf(Club::class, $score->subject);
        $this->assertTrue($score->isPaused());
        $this->assertFalse($score->isAvailableForMatching());
        $this->assertSame(60, $club->reliabilityScore->score);
        $this->assertSame(1, ReliabilityScore::clubs()->paused()->count());
    }

    public function test_reliability_score_unico_por_sujeto(): void
    {
        $user = $this->makeUser();
        $user->reliabilityScore()->create(['score' => 100]);

        $this->expectException(QueryException::class);
        ReliabilityScore::create([
            'subject_type' => 'user', 'subject_id' => $user->id, 'score' => 90,
        ]);
    }

    // ── 6. follows ──────────────────────────────────────────────────────

    public function test_follow_polimorfico_a_club_jugador_y_torneo(): void
    {
        $user    = $this->makeUser();
        $club    = $this->makeClub();
        $jugador = $this->makeUser();
        $torneo  = $this->makeTournament($this->makeUser());

        $fClub = $club->followers()->create(['user_id' => $user->id]);
        $jugador->followers()->create(['user_id' => $user->id]);
        Follow::create(['user_id' => $user->id, 'followable_type' => 'tournament', 'followable_id' => $torneo->id]);

        $this->assertTrue($fClub->isFollowingClub());
        $this->assertInstanceOf(Club::class, $fClub->followable);
        $this->assertInstanceOf(User::class, $fClub->user);
        $this->assertSame(3, $user->follows()->count());
        $this->assertSame(1, $club->followers()->count());
        $this->assertSame(1, Follow::ofType('tournament')->count());
    }

    public function test_follow_no_se_duplica(): void
    {
        $user = $this->makeUser();
        $club = $this->makeClub();
        $club->followers()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        $club->followers()->create(['user_id' => $user->id]);
    }

    // ── 7. Mensajería ───────────────────────────────────────────────────

    public function test_conversation_con_participantes_y_mensajes(): void
    {
        $op = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_RIVAL, 'city' => 'Asunción', 'status' => 'en_negociacion',
            'club_id' => $this->makeClub()->id,
        ]);
        $userA = $this->makeUser();
        $userB = $this->makeUser();

        $conv = Conversation::create([
            'subject_type'    => 'opportunity',
            'subject_id'      => $op->id,
            'last_message_at' => now(),
        ]);
        $conv->participants()->create(['user_id' => $userA->id]);
        $conv->participants()->create(['user_id' => $userB->id]);

        $msg = $conv->messages()->create([
            'sender_user_id' => $userA->id,
            'body'           => 'Coordinamos el sábado?',
        ]);

        $this->assertTrue($conv->isLinked());
        $this->assertInstanceOf(Opportunity::class, $conv->subject);
        $this->assertCount(2, $conv->participants);
        $this->assertCount(1, $conv->messages);
        $this->assertTrue($msg->isStructured());
        $this->assertInstanceOf(User::class, $msg->senderUser);
        $this->assertInstanceOf(ConversationParticipant::class, $conv->participants->first());
    }

    public function test_conversation_sin_vinculo(): void
    {
        $conv = Conversation::create([]);
        $this->assertFalse($conv->isLinked());
    }

    // ── 8. content_reports ──────────────────────────────────────────────

    public function test_content_report_polimorfico_y_flujo_de_revision(): void
    {
        $reporter = $this->makeUser();
        $admin    = $this->makeUser(['role' => 'admin']);
        $op       = Opportunity::create([
            'type' => Opportunity::TYPE_BUSCAR_EQUIPO, 'city' => 'Asunción', 'status' => 'abierta',
            'user_id' => $this->makeUser()->id,
        ]);

        $report = ContentReport::create([
            'reporter_user_id' => $reporter->id,
            'reportable_type'  => 'opportunity',
            'reportable_id'    => $op->id,
            'reason'           => 'spam',
            'details'          => 'Publicación repetida',
        ]);

        $this->assertTrue($report->isPendiente());
        $this->assertInstanceOf(Opportunity::class, $report->reportable);
        $this->assertInstanceOf(User::class, $report->reporter);
        $this->assertSame(1, ContentReport::pendientes()->count());

        $report->update([
            'status'              => ContentReport::STATUS_RESUELTO,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at'         => now(),
            'admin_notes'         => 'Eliminada',
        ]);

        $this->assertTrue($report->fresh()->isResuelto());
        $this->assertSame($admin->id, $report->fresh()->reviewer->id);
    }
}
