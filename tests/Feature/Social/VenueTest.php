<?php

namespace Tests\Feature\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Social\Venue;
use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FutGO Social — Sesión S3-B: Venues (canchas).
 *
 * Cubre: registrar cancha (usuario autenticado), perfil público sin login,
 * búsqueda por ciudad, vincular a amistoso y oportunidad, restricción de
 * edición, partidos jugados en el perfil de cancha.
 */
class VenueTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'User ' . uniqid(),
            'email'     => uniqid('v') . '@test.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
            'role'      => 'user',
            'modules'   => 'torneos',
        ], $extra));
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

    private function makeVenue(User $user, array $extra = []): Venue
    {
        return Venue::create(array_merge([
            'name'                  => 'Cancha ' . uniqid(),
            'city'                  => 'Asunción',
            'registered_by_user_id' => $user->id,
        ], $extra));
    }

    // ── 1. Registrar cancha (usuario autenticado) ─────────────────────────

    public function test_usuario_autenticado_puede_registrar_cancha(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post(route('social.canchas.store'), [
            'name'            => 'Complejo Deportivo Norte',
            'city'            => 'Asunción',
            'address'         => 'Av. España 1234',
            'surface_type'    => 'cesped_sintetico',
            'approx_capacity' => 22,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('venues', [
            'name'                  => 'Complejo Deportivo Norte',
            'city'                  => 'Asunción',
            'registered_by_user_id' => $user->id,
        ]);
    }

    public function test_guest_no_puede_registrar_cancha(): void
    {
        $this->post(route('social.canchas.store'), [
            'name' => 'Cancha Test',
            'city' => 'Asunción',
        ])->assertRedirect(route('login'));
    }

    // ── 2. Perfil público accesible sin login ─────────────────────────────

    public function test_perfil_publico_accesible_sin_login(): void
    {
        $user  = $this->makeUser();
        $venue = $this->makeVenue($user, ['name' => 'Cancha Pública', 'city' => 'Luque']);

        $this->get(route('social.canchas.show', $venue->slug))
            ->assertOk()
            ->assertSee('Cancha Pública');
    }

    public function test_cancha_inactiva_retorna_404(): void
    {
        $user  = $this->makeUser();
        $venue = $this->makeVenue($user, ['is_active' => false]);

        $this->get(route('social.canchas.show', $venue->slug))
            ->assertNotFound();
    }

    // ── 3. Búsqueda por ciudad ────────────────────────────────────────────

    public function test_busqueda_por_ciudad_retorna_resultados_correctos(): void
    {
        $user = $this->makeUser();
        $this->makeVenue($user, ['name' => 'Cancha Asunción A', 'city' => 'Asunción']);
        $this->makeVenue($user, ['name' => 'Cancha Asunción B', 'city' => 'Asunción']);
        $this->makeVenue($user, ['name' => 'Cancha Luque',      'city' => 'Luque']);

        $response = $this->getJson(route('social.canchas.search', ['ciudad' => 'Asunción']));

        $response->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['city' => 'Asunción']);

        // La de Luque no debe aparecer
        $this->assertStringNotContainsString('Cancha Luque', $response->content());
    }

    public function test_busqueda_filtra_por_nombre(): void
    {
        $user = $this->makeUser();
        $this->makeVenue($user, ['name' => 'Estadio Central', 'city' => 'Asunción']);
        $this->makeVenue($user, ['name' => 'Polideportivo Sur', 'city' => 'Asunción']);

        $response = $this->getJson(route('social.canchas.search', ['q' => 'Central']));

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Estadio Central']);
    }

    // ── 4. Vincular cancha a amistoso ─────────────────────────────────────

    public function test_amistoso_puede_vincularse_a_cancha(): void
    {
        $cap1  = $this->makeUser();
        $cap2  = $this->makeUser();
        $club1 = $this->makeClub($cap1);
        $club2 = $this->makeClub($cap2);
        $venue = $this->makeVenue($cap1);

        $match = FriendlyMatch::create([
            'home_club_id' => $club1->id,
            'away_club_id' => $club2->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'venue_id'     => $venue->id,
            'scheduled_at' => now()->addDays(2),
        ]);

        $this->assertEquals($venue->id, $match->fresh()->venue_id);
        $this->assertEquals($venue->name, $match->fresh()->venue->name);
    }

    // ── 5. Vincular cancha a oportunidad ──────────────────────────────────

    public function test_oportunidad_puede_vincularse_a_cancha(): void
    {
        $user  = $this->makeUser();
        $club  = $this->makeClub($user);
        $venue = $this->makeVenue($user);

        $opportunity = Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_RIVAL,
            'user_id'        => $user->id,
            'club_id'        => $club->id,
            'city'           => 'Asunción',
            'required_level' => 'recreativo',
            'venue_id'       => $venue->id,
            'status'         => Opportunity::STATUS_ABIERTA,
            'payload'        => [],
        ]);

        $this->assertEquals($venue->id, $opportunity->fresh()->venue_id);
        $this->assertEquals($venue->name, $opportunity->fresh()->venue->name);
    }

    // ── 6. Solo el registrador o admin puede editar ───────────────────────

    public function test_solo_registrador_puede_editar(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $venue = $this->makeVenue($owner, ['name' => 'Original']);

        // El propietario puede editar
        $this->actingAs($owner)->patch(route('social.canchas.update', $venue->slug), [
            'name' => 'Actualizada',
            'city' => 'Asunción',
        ])->assertRedirect();

        $this->assertDatabaseHas('venues', ['name' => 'Actualizada']);
    }

    public function test_otro_usuario_no_puede_editar(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $venue = $this->makeVenue($owner, ['name' => 'Original']);

        $this->actingAs($other)->patch(route('social.canchas.update', $venue->slug), [
            'name' => 'Hackeada',
            'city' => 'Asunción',
        ])->assertForbidden();

        $this->assertDatabaseHas('venues', ['name' => 'Original']);
    }

    public function test_admin_puede_editar_cualquier_cancha(): void
    {
        $owner = $this->makeUser();
        $admin = $this->makeUser(['role' => 'admin']);
        $venue = $this->makeVenue($owner, ['name' => 'Original']);

        $this->actingAs($admin)->patch(route('social.canchas.update', $venue->slug), [
            'name' => 'Editada por admin',
            'city' => 'Asunción',
        ])->assertRedirect();

        $this->assertDatabaseHas('venues', ['name' => 'Editada por admin']);
    }

    // ── 7. Partidos jugados aparecen en el perfil ─────────────────────────

    public function test_amistoso_jugado_aparece_en_perfil_de_cancha(): void
    {
        $cap1  = $this->makeUser();
        $cap2  = $this->makeUser();
        $club1 = $this->makeClub($cap1);
        $club2 = $this->makeClub($cap2);
        $venue = $this->makeVenue($cap1, ['name' => 'Cancha Vista']);

        FriendlyMatch::create([
            'home_club_id'   => $club1->id,
            'away_club_id'   => $club2->id,
            'status'         => FriendlyMatch::STATUS_JUGADO,
            'venue_id'       => $venue->id,
            'scheduled_at'   => now()->subDays(2),
            'result_agreement'  => FriendlyMatch::AGREEMENT_ACORDADO,
            'final_home_score'  => 2,
            'final_away_score'  => 1,
        ]);

        $response = $this->get(route('social.canchas.show', $venue->slug));

        $response->assertOk()
            ->assertSee($club1->name)
            ->assertSee($club2->name);
    }

    // ── 8. Slug único generado automáticamente ────────────────────────────

    public function test_slug_generado_automaticamente(): void
    {
        $user  = $this->makeUser();
        $venue = Venue::create([
            'name'                  => 'Club Deportivo Asunción',
            'city'                  => 'Asunción',
            'registered_by_user_id' => $user->id,
        ]);

        $this->assertNotEmpty($venue->slug);
        $this->assertStringContainsString('club-deportivo-asuncion', $venue->slug);
    }
}
