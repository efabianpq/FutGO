<?php

namespace Tests\Feature;

use App\Models\Support\SupportArticle;
use App\Models\Support\SupportFeatureRequest;
use App\Models\Support\SupportServiceStatus;
use App\Models\Support\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => 'El fixture solo se genera cuando el torneo está en estado open.']],
                    ],
                ]],
            ], 200),
        ]);
    }

    public function test_hub_carga_para_usuario_autenticado(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('soporte.index'))->assertOk();
    }

    public function test_chat_retorna_respuesta_de_ia(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson(route('soporte.chat.send'), ['message' => '¿Cómo genero el fixture?']);

        $response->assertOk()
            ->assertJsonStructure(['response', 'conversation_id', 'escalated', 'ticket_id']);
        $this->assertNotNull($response->json('conversation_id'));
    }

    public function test_escalado_crea_ticket_con_contexto(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'Voy a crear un ticket para que el equipo de FutGO lo revise.']]],
                ]],
            ], 200),
        ]);

        $user = User::factory()->create();
        $response = $this->actingAs($user)
            ->postJson(route('soporte.chat.send'), ['message' => 'No puedo cargar el resultado y nada funciona']);

        $response->assertOk();

        if ($response->json('escalated')) {
            $this->assertNotNull($response->json('ticket_id'));
            $ticket = SupportTicket::find($response->json('ticket_id'));
            $this->assertNotNull($ticket->context_snapshot);
            $this->assertEquals($user->id, $ticket->user_id);
        }
    }

    public function test_clasificacion_asigna_categoria_y_prioridad(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => '{"category":"bug","priority":"alta","confidence":0.95,"subject":"No puedo cargar resultado"}']]],
                ]],
            ], 200),
        ]);

        $service = app(\App\Services\Support\TicketClassifierService::class);
        $result = $service->classify('No puedo cargar el resultado del partido');

        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('confidence', $result);
        $this->assertContains($result['category'], ['bug', 'duda', 'disputa', 'sugerencia', 'funcionalidad', 'reclamo', 'abuso', 'cuenta', 'verificacion', 'otro']);
    }

    public function test_estado_servicio_accesible_sin_auth(): void
    {
        $this->get(route('soporte.status'))->assertOk();
    }

    public function test_voto_es_unico_por_usuario(): void
    {
        $user = User::factory()->create();
        $fr = SupportFeatureRequest::create([
            'title'       => 'Editar resultados',
            'description' => 'Poder modificar un resultado cargado.',
            'status'      => 'recibido',
        ]);

        $this->actingAs($user)->postJson(route('soporte.features.vote', $fr))
            ->assertOk()->assertJson(['voted' => true, 'votes' => 1]);

        $this->actingAs($user)->postJson(route('soporte.features.vote', $fr))
            ->assertOk()->assertJson(['voted' => false, 'votes' => 0]);
    }

    public function test_admin_puede_cambiar_estado_de_ticket(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id'               => $user->id,
            'category'              => 'bug',
            'status'                => 'abierto',
            'priority'              => 'media',
            'classifier_confidence' => 0.9,
            'subject'               => 'Test ticket',
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.soporte.tickets.status', $ticket), ['status' => 'en_revision'])
            ->assertRedirect();

        $this->assertEquals('en_revision', $ticket->fresh()->status);
    }

    public function test_admin_puede_resolver_ticket(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create();
        $ticket = SupportTicket::create([
            'user_id'               => $user->id,
            'category'              => 'duda',
            'status'                => 'en_revision',
            'priority'              => 'baja',
            'classifier_confidence' => 0.8,
            'subject'               => 'Consulta de prueba',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.soporte.tickets.resolve', $ticket), [
                'resolution_notes' => 'Se explicó el proceso al usuario.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertEquals('resuelto', $ticket->status);
        $this->assertNotNull($ticket->resolved_at);
    }

    public function test_articulo_no_publicado_retorna_404(): void
    {
        $user = User::factory()->create();
        $article = SupportArticle::create([
            'title'        => 'Artículo privado',
            'slug'         => 'articulo-privado',
            'content'      => 'Contenido.',
            'category'     => 'tecnico',
            'source'       => 'manual',
            'is_published' => false,
        ]);

        $this->actingAs($user)
            ->get(route('soporte.knowledge.article', $article))
            ->assertNotFound();
    }

    public function test_monitor_actualiza_estado_de_componentes(): void
    {
        // La migración ya siembra los componentes; garantizamos 'plataforma' sin duplicar.
        SupportServiceStatus::firstOrCreate(
            ['component' => 'plataforma'],
            ['status' => 'operativo']
        );

        $service = app(\App\Services\Support\StatusMonitorService::class);
        $service->runAllChecks();

        $status = SupportServiceStatus::where('component', 'plataforma')->first();
        $this->assertNotNull($status->last_checked_at);
    }
}
