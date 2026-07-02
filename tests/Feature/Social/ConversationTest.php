<?php

namespace Tests\Feature\Social;

use App\Models\Social\Conversation;
use App\Models\Social\ContentReport;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Message;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Torneos\Club;
use App\Models\User;
use App\Services\Social\ConversationService;
use App\Services\Social\OpportunityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Fase 2 · Sesión S2-B: mensajería libre en conversaciones existentes.
 *
 * Cubre: la conversación se crea al aceptar una oportunidad (con primer mensaje
 * estructurado), solo los participantes acceden, un mensaje aparece para ambos,
 * filtro de contenido/longitud, compartir contacto como mensaje libre, y que
 * reportar un mensaje genera un content_report con el mensaje como evidencia.
 */
class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'User ' . uniqid(),
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

    private function service(): OpportunityService
    {
        return app(OpportunityService::class);
    }

    private function conversations(): ConversationService
    {
        return app(ConversationService::class);
    }

    /**
     * Crea una oportunidad BUSCAR_JUGADOR aceptada y devuelve [conversación,
     * publicante, respondente]. Es el caso más simple (un usuario de cada lado).
     */
    private function acceptedJugadorConversation(): array
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain, 'Local FC');
        $player  = $this->makeUser();

        $opportunity = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_JUGADOR,
            'user_id'        => $captain->id,
            'club_id'        => $club->id,
            'city'           => 'Asunción',
            'required_level' => null,
            'status'         => Opportunity::STATUS_ABIERTA,
            'payload'        => ['cupos' => 1],
        ]);

        $response = $opportunity->responses()->create([
            'user_id' => $player->id,
            'status'  => OpportunityResponse::STATUS_PENDIENTE,
        ]);

        $this->service()->accept($response);

        $conversation = Conversation::where('subject_type', 'opportunity')
            ->where('subject_id', $opportunity->id)
            ->firstOrFail();

        return [$conversation, $captain, $player];
    }

    // ── 1. La conversación se crea al aceptar una oportunidad ────────────

    public function test_conversacion_se_crea_al_aceptar_oportunidad(): void
    {
        [$conversation, $captain, $player] = $this->acceptedJugadorConversation();

        // Ambos actores quedan como participantes.
        $this->assertEqualsCanonicalizing(
            [$captain->id, $player->id],
            $conversation->participants()->pluck('user_id')->all(),
        );

        // El primer mensaje es estructurado (resumen del acuerdo, sin emisor humano).
        $first = $conversation->messages()->orderBy('id')->first();
        $this->assertNotNull($first);
        $this->assertSame(Message::TYPE_STRUCTURED, $first->type);
        $this->assertNull($first->sender_user_id);
        $this->assertNotNull($conversation->last_message_at);
    }

    public function test_aceptar_buscar_rival_vincula_la_conversacion_al_amistoso(): void
    {
        $homeCap = $this->makeUser();
        $awayCap = $this->makeUser();
        $home    = $this->makeClub($homeCap, 'Local FC');
        $away    = $this->makeClub($awayCap, 'Visita FC');

        $opportunity = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $homeCap->id,
            'club_id'        => $home->id,
            'city'           => 'Asunción',
            'required_level' => null,
            'status'         => Opportunity::STATUS_ABIERTA,
            'payload'        => [],
            'window_start'   => now()->addDays(3),
        ]);

        $response = $opportunity->responses()->create([
            'user_id' => $awayCap->id,
            'club_id' => $away->id,
            'status'  => OpportunityResponse::STATUS_PENDIENTE,
        ]);

        $this->service()->accept($response);

        $friendly = FriendlyMatch::where('opportunity_id', $opportunity->id)->firstOrFail();

        // La conversación se vincula al amistoso, no a la oportunidad.
        $conversation = Conversation::where('subject_type', 'friendly_match')
            ->where('subject_id', $friendly->id)
            ->first();
        $this->assertNotNull($conversation);
        $this->assertEqualsCanonicalizing(
            [$homeCap->id, $awayCap->id],
            $conversation->participants()->pluck('user_id')->all(),
        );
    }

    // ── 2. Solo los participantes acceden ───────────────────────────────

    public function test_solo_los_participantes_acceden(): void
    {
        [$conversation, $captain, $player] = $this->acceptedJugadorConversation();
        $intruso = $this->makeUser();

        $this->actingAs($captain)->get(route('social.conversaciones.show', $conversation))->assertOk();
        $this->actingAs($player)->get(route('social.conversaciones.show', $conversation))->assertOk();

        // Un tercero no participante recibe 403.
        $this->actingAs($intruso)->get(route('social.conversaciones.show', $conversation))->assertForbidden();

        // Tampoco puede escribir.
        $this->actingAs($intruso)
            ->post(route('social.conversaciones.store', $conversation), ['body' => 'Hola'])
            ->assertForbidden();
    }

    // ── 3. Un mensaje aparece para ambos participantes ──────────────────

    public function test_mensaje_aparece_para_ambos(): void
    {
        [$conversation, $captain, $player] = $this->acceptedJugadorConversation();

        $this->actingAs($captain)
            ->post(route('social.conversaciones.store', $conversation), ['body' => 'Bienvenido al equipo, coordinemos.'])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_user_id'  => $captain->id,
            'type'            => Message::TYPE_FREE,
        ]);

        // El otro participante lo ve en el hilo.
        $this->actingAs($player)->get(route('social.conversaciones.show', $conversation))
            ->assertOk()
            ->assertSee('Bienvenido al equipo, coordinemos.');

        // Y el emisor también.
        $this->actingAs($captain)->get(route('social.conversaciones.show', $conversation))
            ->assertOk()
            ->assertSee('Bienvenido al equipo, coordinemos.');
    }

    public function test_mensaje_con_lenguaje_prohibido_se_rechaza(): void
    {
        [$conversation, $captain] = $this->acceptedJugadorConversation();

        $this->actingAs($captain)
            ->post(route('social.conversaciones.store', $conversation), ['body' => 'sos un idiota'])
            ->assertSessionHasErrors('body');

        // No se persistió ningún mensaje libre.
        $this->assertSame(0, Message::where('type', Message::TYPE_FREE)->count());
    }

    // ── 4. Compartir contacto queda como un mensaje más ─────────────────

    public function test_compartir_contacto_publica_un_mensaje_libre(): void
    {
        [$conversation, $captain] = $this->acceptedJugadorConversation();
        $captain->update(['phone_whatsapp' => '0981123456']);

        $this->actingAs($captain)
            ->post(route('social.conversaciones.share-contact', $conversation))
            ->assertRedirect();

        $message = Message::where('conversation_id', $conversation->id)
            ->where('type', Message::TYPE_FREE)
            ->latest('id')->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('0981123456', $message->body);
    }

    // ── 5. Reportar un mensaje genera un content_report ─────────────────

    public function test_reportar_mensaje_genera_content_report(): void
    {
        [$conversation, $captain, $player] = $this->acceptedJugadorConversation();

        // El capitán escribe; el jugador lo reporta.
        $msg = $this->conversations()->postMessage($conversation, $captain, 'Mensaje cuestionable', $conversation->participantFor($captain)?->club_id);

        $this->actingAs($player)
            ->post(route('social.conversaciones.messages.report', $msg), [
                'reason'  => 'contenido_inapropiado',
                'details' => 'No corresponde.',
            ])
            ->assertSessionHas('status');

        $this->assertDatabaseHas('content_reports', [
            'reporter_user_id' => $player->id,
            'reportable_type'  => 'message',
            'reportable_id'    => $msg->id,
            'reason'           => 'contenido_inapropiado',
            'status'           => ContentReport::STATUS_PENDIENTE,
        ]);
    }

    public function test_no_se_puede_reportar_el_propio_mensaje(): void
    {
        [$conversation, $captain] = $this->acceptedJugadorConversation();

        $msg = $this->conversations()->postMessage($conversation, $captain, 'Mi mensaje', null);

        $this->actingAs($captain)
            ->post(route('social.conversaciones.messages.report', $msg), ['reason' => 'spam'])
            ->assertForbidden();

        $this->assertSame(0, ContentReport::count());
    }

    public function test_no_participante_no_puede_reportar_mensaje(): void
    {
        [$conversation, $captain] = $this->acceptedJugadorConversation();
        $intruso = $this->makeUser();

        $msg = $this->conversations()->postMessage($conversation, $captain, 'Hola', null);

        $this->actingAs($intruso)
            ->post(route('social.conversaciones.messages.report', $msg), ['reason' => 'spam'])
            ->assertForbidden();

        $this->assertSame(0, ContentReport::count());
    }

    // ── 6. Lista de conversaciones y contador de no leídos ──────────────

    public function test_index_lista_la_conversacion_y_marca_leido_al_abrir(): void
    {
        [$conversation, $captain, $player] = $this->acceptedJugadorConversation();

        // El publicante escribe → para el jugador hay no leídos.
        $this->conversations()->postMessage($conversation, $captain, 'Hola, coordinemos', null);

        $this->assertSame(1, $this->conversations()->unreadCount($player->fresh()));

        // El jugador ve su conversación en la lista.
        $this->actingAs($player)->get(route('social.conversaciones.index'))
            ->assertOk()
            ->assertSee('Local FC');

        // Abrir el hilo marca todo como leído.
        $this->actingAs($player)->get(route('social.conversaciones.show', $conversation))->assertOk();
        $this->assertSame(0, $this->conversations()->unreadCount($player->fresh()));
    }
}
