<?php

namespace Tests\Feature\Social;

use App\Models\Social\FeedEvent;
use App\Models\Social\Opportunity;
use App\Models\Torneos\Club;
use App\Models\User;
use App\Services\Social\FeedService;
use App\Services\Social\FollowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Fase 1 · Sesión S1-E: seguir entidades y Feed de sistema.
 *
 * Cubre: toggle de seguir/dejar de seguir, relevancia del Feed por follows y por
 * ciudad/nivel, contenido de entrada para usuario nuevo y el contador de no
 * leídos (que baja al marcar como leído).
 */
class FollowAndFeedTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'User ' . uniqid(),
            'email'     => uniqid('user') . '@test.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
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

    private function feed(): FeedService
    {
        return app(FeedService::class);
    }

    private function follows(): FollowService
    {
        return app(FollowService::class);
    }

    // ── 1. Seguir / dejar de seguir (toggle) ────────────────────────────

    public function test_seguir_y_dejar_de_seguir_un_club(): void
    {
        $user = $this->makeUser();
        $club = $this->makeClub($this->makeUser(), 'Aurora FC');

        // Seguir.
        $this->actingAs($user)
            ->post(route('social.follow.toggle', ['type' => 'club', 'id' => $club->id]))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('follows', [
            'user_id'         => $user->id,
            'followable_type' => 'club',
            'followable_id'   => $club->id,
        ]);
        $this->assertTrue($this->follows()->isFollowing($user, $club));

        // Volver a togglear = dejar de seguir.
        $this->actingAs($user)
            ->post(route('social.follow.toggle', ['type' => 'club', 'id' => $club->id]));

        $this->assertDatabaseMissing('follows', [
            'user_id'         => $user->id,
            'followable_type' => 'club',
            'followable_id'   => $club->id,
        ]);
        $this->assertFalse($this->follows()->isFollowing($user, $club));
    }

    public function test_no_se_puede_seguir_a_si_mismo(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('social.follow.toggle', ['type' => 'user', 'id' => $user->id]))
            ->assertStatus(422);

        $this->assertSame(0, \App\Models\Social\Follow::count());
    }

    // ── 2. El Feed muestra un evento de un club seguido ─────────────────

    public function test_feed_muestra_evento_de_club_seguido(): void
    {
        $user = $this->makeUser();                       // sin ciudad: solo follows
        $club = $this->makeClub($this->makeUser(), 'Local FC');
        $rival = $this->makeClub($this->makeUser(), 'Visita FC');

        $this->follows()->toggle($user, $club);

        // El sistema registra el resultado de un amistoso del club seguido.
        $this->feed()->record(FeedEvent::TYPE_RESULTADO_AMISTOSO, $club, $rival, [
            'payload' => ['home' => 'Local FC', 'away' => 'Visita FC', 'home_score' => 2, 'away_score' => 1],
        ]);

        $this->actingAs($user)->get(route('social.feed.index'))
            ->assertOk()
            ->assertSee('Local FC')
            ->assertSee('Resultado de amistoso');
    }

    // ── 3. El Feed NO muestra el evento de un club no seguido ───────────

    public function test_feed_no_muestra_evento_de_club_no_seguido(): void
    {
        $user = $this->makeUser();                       // sin ciudad ni follows
        $ajeno = $this->makeClub($this->makeUser(), 'Secreto FC');

        // Evento sin distribución por ciudad: solo relevante para quien siga al club.
        $this->feed()->record(FeedEvent::TYPE_RESULTADO_AMISTOSO, $ajeno, null, [
            'payload' => ['home' => 'Secreto FC', 'away' => 'Otro FC', 'home_score' => 0, 'away_score' => 0],
        ]);

        $this->actingAs($user)->get(route('social.feed.index'))
            ->assertOk()
            ->assertDontSee('Secreto FC');
    }

    // ── 4. Usuario nuevo (sin follows) ve oportunidades de su ciudad ────

    public function test_usuario_nuevo_ve_oportunidades_de_su_ciudad(): void
    {
        $user = $this->makeUser(['city' => 'Bogotá']);
        $publisher = $this->makeUser();

        // Oportunidad activa en su ciudad (contenido de entrada del Feed vacío).
        Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_EQUIPO,
            'user_id'        => $publisher->id,
            'city'           => 'Bogotá',
            'required_level' => null,
            'status'         => Opportunity::STATUS_ABIERTA,
            'payload'        => ['posicion' => 'arquero'],
            'window_start'   => now()->addDays(2),
        ]);

        $this->actingAs($user)->get(route('social.feed.index'))
            ->assertOk()
            ->assertSee('Oportunidades en tu ciudad')
            ->assertSee('Bogotá');
    }

    public function test_usuario_nuevo_sin_ciudad_ve_feed_vacio_sin_error(): void
    {
        $user = $this->makeUser();   // sin ciudad ni follows

        $this->actingAs($user)->get(route('social.feed.index'))
            ->assertOk()
            ->assertSee('Tu Feed todavía está tranquilo');
    }

    // ── 5. El contador de no leídos baja al marcar como leído ───────────

    public function test_notificacion_se_marca_como_leida(): void
    {
        $user = $this->makeUser(['city' => 'Cali']);

        // El usuario nace en T0; el evento ocurre un minuto después.
        $this->travel(1)->minutes();

        $this->feed()->record(FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA, null, null, [
            'city'    => 'Cali',
            'payload' => ['author' => 'Tigres FC', 'type_label' => 'Busca rival', 'city' => 'Cali'],
        ]);

        // Hay 1 no leído.
        $this->assertSame(1, $this->feed()->unreadCount($user->fresh()));

        // Abrir el Feed lo marca como leído.
        $this->actingAs($user)->get(route('social.feed.index'))->assertOk();

        $this->assertSame(0, $this->feed()->unreadCount($user->fresh()));
    }

    public function test_marcar_leido_por_endpoint_baja_el_contador(): void
    {
        $user = $this->makeUser(['city' => 'Medellín']);

        $this->travel(1)->minutes();
        $this->feed()->record(FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA, null, null, [
            'city'    => 'Medellín',
            'payload' => ['author' => 'Equipo X', 'type_label' => 'Busca jugador', 'city' => 'Medellín'],
        ]);

        $this->assertSame(1, $this->feed()->unreadCount($user->fresh()));

        $this->actingAs($user)->post(route('social.feed.read'))->assertSessionHas('status');

        $this->assertSame(0, $this->feed()->unreadCount($user->fresh()));
    }

    // ── 6. Relevancia por ciudad respeta el nivel ──────────────────────

    public function test_evento_de_otra_ciudad_no_es_relevante(): void
    {
        $user = $this->makeUser(['city' => 'Cali']);

        $this->feed()->record(FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA, null, null, [
            'city'    => 'Bogotá',
            'payload' => ['author' => 'Foráneo FC', 'type_label' => 'Busca rival', 'city' => 'Bogotá'],
        ]);

        $this->actingAs($user)->get(route('social.feed.index'))
            ->assertOk()
            ->assertDontSee('Foráneo FC');
    }
}
