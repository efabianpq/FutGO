<?php

namespace Tests\Feature\Torneos;

use App\Exceptions\Torneos\ProfileClaimException;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\ProfileClaim;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Notifications\ProfileClaimResolvedNotification;
use App\Notifications\ProfileClaimSubmittedNotification;
use App\Services\Torneos\ProfileClaimService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * TX-1 — Reclamo de perfil de jugadores "por verificar" (Limitación #2).
 */
class ProfileClaimTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function activeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge([
'role' => 'user',        ], $attrs));
    }

    private function makeTournament(User $admin): Tournament
    {
        $t = Tournament::create([
            'name'               => 'Copa ' . uniqid(),
            'slug'               => 'copa-' . uniqid(),
            'sport'              => 'futbol',
            'status'             => 'in_progress',
            'format'             => 'groups_and_knockout',
            'groups_count'       => 2,
            'teams_per_group'    => 4,
            'classifies_per_group' => 2,
            'created_by_user_id' => $admin->id,
        ]);
        $t->admins()->attach($admin->id);

        return $t;
    }

    /**
     * Crea un registro 'por_verificar' (sin cuenta) con su historial: club +
     * club_player + team_player + player_stats. Devuelve [club, clubPlayer, teamPlayer].
     */
    private function porVerificarRecord(
        string $document,
        string $name,
        ?User $captain,
        Tournament $t,
        array $stats = []
    ): array {
        $club = Club::create([
            'name'               => 'Club ' . uniqid(),
            'slug'               => 'club-' . uniqid(),
            'created_by_user_id' => $captain?->id ?? User::factory()->create()->id,
            'captain_user_id'    => $captain?->id,
        ]);

        if ($captain) {
            ClubPlayer::create([
                'club_id'             => $club->id,
                'user_id'             => $captain->id,
                'is_captain'          => true,
                'verification_status' => 'registrado',
                'status'              => 'active',
            ]);
        }

        $cp = ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => null,
            'verification_status' => 'por_verificar',
            'full_name'           => $name,
            'document'            => $document,
            'status'              => 'active',
        ]);

        $team = Team::create([
            'tournament_id'   => $t->id,
            'club_id'         => $club->id,
            'captain_user_id' => $captain?->id,
            'name'            => $club->name,
            'status'          => 'approved',
        ]);

        $tp = TeamPlayer::create([
            'team_id'             => $team->id,
            'user_id'             => null,
            'verification_status' => 'por_verificar',
            'full_name'           => $name,
            'document'            => $document,
            'status'              => 'active',
        ]);

        PlayerStat::create(array_merge([
            'tournament_id'  => $t->id,
            'team_player_id' => $tp->id,
            'goals' => 0, 'assists' => 0, 'yellow_cards' => 0, 'red_cards' => 0,
            'minutes_played' => 0, 'matches_played' => 0,
            'wins' => 0, 'draws' => 0, 'losses' => 0, 'clean_sheets' => 0, 'mvps' => 0,
        ], $stats));

        return compact('club', 'cp', 'team', 'tp');
    }

    private function service(): ProfileClaimService
    {
        return app(ProfileClaimService::class);
    }

    // ─── 1. El registro detecta candidatos por documento ──────────────────────

    public function test_registro_detecta_candidato_por_documento(): void
    {
        $captain = $this->activeUser();
        $t       = $this->makeTournament($captain);
        $this->porVerificarRecord('99887766', 'Pedro Gol', $captain, $t);

        $this->post(route('register.store'), [
            'nombre' => 'Pedro', 'apellido' => 'Gol',
            'email' => 'pedro@test.com',
            'telefono' => '3001234567',
            'documento' => '99887766',
            'birthdate' => '1990-01-01',
            'password' => 'SuperSecret123',
            'password_confirmation' => 'SuperSecret123',
            'accept_terms' => '1',
            'accept_privacy' => '1',
        ])
            ->assertRedirect(route('torneos.mi-carrera'))
            ->assertSessionHas('claim_candidates', 1);

        $user = User::where('email', 'pedro@test.com')->firstOrFail();
        $this->assertSame('99887766', $user->document);
        $this->assertSame(1, $this->service()->countCandidatesFor($user));
    }

    // ─── 2. El reclamo queda pendiente y notifica al capitán ──────────────────

    public function test_reclamo_queda_pendiente_y_notifica_al_capitan(): void
    {
        Notification::fake();

        $captain  = $this->activeUser();
        $claimant = $this->activeUser(['document' => '12345678']);
        $t        = $this->makeTournament($captain);
        $rec      = $this->porVerificarRecord('12345678', 'Juan Crack', $captain, $t);

        $this->actingAs($claimant)
            ->post(route('torneos.reclamos.store'), ['club_player_id' => $rec['cp']->id])
            ->assertRedirect();

        $this->assertDatabaseHas('profile_claims', [
            'user_id'        => $claimant->id,
            'club_player_id' => $rec['cp']->id,
            'status'         => 'pending',
        ]);

        // El registro sigue intacto hasta la aprobación.
        $this->assertNull($rec['cp']->fresh()->user_id);

        Notification::assertSentTo($captain, ProfileClaimSubmittedNotification::class);
    }

    // ─── 3. La aprobación vincula y transfiere el historial ───────────────────

    public function test_aprobacion_vincula_y_transfiere_historial(): void
    {
        Notification::fake();

        $captain  = $this->activeUser();
        $claimant = $this->activeUser(['document' => '12345678']);
        $t        = $this->makeTournament($captain);
        $rec      = $this->porVerificarRecord('12345678', 'Juan Crack', $captain, $t, [
            'goals' => 7, 'assists' => 3, 'matches_played' => 5, 'minutes_played' => 450,
        ]);

        $claim = $this->service()->claim($claimant, $rec['cp']);

        // El capitán aprueba vía HTTP (prueba ruta + autorización).
        $this->actingAs($captain)
            ->post(route('torneos.reclamos.approve', $claim))
            ->assertRedirect();

        // 1. El club_player quedó vinculado y 'registrado'.
        $cp = $rec['cp']->fresh();
        $this->assertSame($claimant->id, $cp->user_id);
        $this->assertSame('registrado', $cp->verification_status);

        // 2. El team_player (historial) heredó la cuenta.
        $tp = $rec['tp']->fresh();
        $this->assertSame($claimant->id, $tp->user_id);
        $this->assertSame('registrado', $tp->verification_status);

        // 3. El acumulado del jugador se consolidó con las stats heredadas.
        $career = PlayerCareerStat::where('user_id', $claimant->id)->first();
        $this->assertNotNull($career);
        $this->assertSame(7, (int) $career->goals);
        $this->assertSame(5, (int) $career->matches_played);

        // 4. El reclamo quedó aprobado.
        $this->assertSame('approved', $claim->fresh()->status);

        Notification::assertSentTo($claimant, ProfileClaimResolvedNotification::class);
    }

    // ─── 4. El rechazo deja el registro sin cambios ───────────────────────────

    public function test_rechazo_deja_el_registro_sin_cambios(): void
    {
        $captain  = $this->activeUser();
        $claimant = $this->activeUser(['document' => '12345678']);
        $t        = $this->makeTournament($captain);
        $rec      = $this->porVerificarRecord('12345678', 'Juan Crack', $captain, $t, ['goals' => 4]);

        $claim = $this->service()->claim($claimant, $rec['cp']);

        $this->actingAs($captain)
            ->post(route('torneos.reclamos.reject', $claim), ['note' => 'No te reconozco'])
            ->assertRedirect();

        // El registro permanente NO cambió.
        $cp = $rec['cp']->fresh();
        $this->assertNull($cp->user_id);
        $this->assertSame('por_verificar', $cp->verification_status);

        // El team_player tampoco.
        $this->assertNull($rec['tp']->fresh()->user_id);

        $claim->refresh();
        $this->assertSame('rejected', $claim->status);
        $this->assertSame('No te reconozco', $claim->resolution_note);

        // El usuario no heredó historial.
        $this->assertNull(PlayerCareerStat::where('user_id', $claimant->id)->value('goals'));
    }

    // ─── 5. Doble reclamo del mismo registro está bloqueado ───────────────────

    public function test_doble_reclamo_del_mismo_registro_bloqueado(): void
    {
        $captain   = $this->activeUser();
        $claimant1 = $this->activeUser(['document' => '12345678']);
        $claimant2 = $this->activeUser(['document' => '12345678']);
        $t         = $this->makeTournament($captain);
        $rec       = $this->porVerificarRecord('12345678', 'Juan Crack', $captain, $t);

        $this->service()->claim($claimant1, $rec['cp']);

        // Un segundo reclamo sobre el mismo registro se bloquea.
        $this->expectException(ProfileClaimException::class);
        $this->service()->claim($claimant2, $rec['cp']);
    }

    // ─── 5b. Un documento no queda vinculado a dos user_id ────────────────────

    public function test_documento_no_se_vincula_a_dos_usuarios(): void
    {
        $captain   = $this->activeUser();
        $claimant1 = $this->activeUser(['document' => '12345678']);
        $claimant2 = $this->activeUser(['document' => '12345678']);
        $t         = $this->makeTournament($captain);
        $rec       = $this->porVerificarRecord('12345678', 'Juan Crack', $captain, $t);

        $claim1 = $this->service()->claim($claimant1, $rec['cp']);
        $this->service()->approve($claim1->fresh(), $captain);

        // Tras vincular, el registro ya no es candidato ni reclamable para otro.
        $this->assertSame(0, $this->service()->countCandidatesFor($claimant2));

        $this->expectException(ProfileClaimException::class);
        $this->service()->claim($claimant2, $rec['cp']->fresh());
    }

    // ─── 6. Sin capitán activo, el reclamo se escala y lo resuelve un admin ───

    public function test_escalamiento_sin_capitan_lo_resuelve_admin(): void
    {
        Notification::fake();

        $admin    = $this->activeUser(['role' => 'admin']);
        $claimant = $this->activeUser(['document' => '55554444']);
        // Torneo creado por el admin; el club NO tiene capitán.
        $t        = $this->makeTournament($admin);
        $rec      = $this->porVerificarRecord('55554444', 'Sin Capi', null, $t, ['goals' => 2]);

        $claim = $this->service()->claim($claimant, $rec['cp']);

        // Nace escalado (no hay capitán que apruebe).
        $this->assertSame('escalated', $claim->status);

        // El admin lo resuelve desde la bandeja escalada.
        $this->actingAs($admin)
            ->post(route('admin.torneos.reclamos.approve', $claim))
            ->assertRedirect();

        $cp = $rec['cp']->fresh();
        $this->assertSame($claimant->id, $cp->user_id);
        $this->assertSame('registrado', $cp->verification_status);
        $this->assertSame('approved', $claim->fresh()->status);
    }
}
