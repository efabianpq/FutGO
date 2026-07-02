<?php

namespace Tests\Feature\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Social\PlayedWithService;
use App\Services\Social\SportsAgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

/**
 * FutGO Social — Fase 2 · Sesión S2-A: "Jugué con vos" + agenda deportiva.
 *
 * Cubre: dos jugadores en el mismo partido aparecen en el historial compartido,
 * la acción "retar" pre-completa la oportunidad, y la agenda lista los eventos del
 * día en orden cronológico, excluyendo torneos/amistosos cancelados.
 */
class PlayedWithAndAgendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function makeUser(array $extra = []): User
    {
        return User::create(array_merge([
            'name'      => 'Jugador ' . uniqid(),
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

    private function playedWith(): PlayedWithService
    {
        return app(PlayedWithService::class);
    }

    private function agenda(): SportsAgendaService
    {
        return app(SportsAgendaService::class);
    }

    /**
     * Crea un torneo con una fase y dos equipos, devuelve [tournament, phase, teamA, teamB].
     */
    private function makeTournamentWithTeams(User $capA, User $capB, string $status = 'in_progress'): array
    {
        $admin = $this->makeUser(['role' => 'user']);

        $tournament = Tournament::create([
            'name'               => 'Torneo ' . uniqid(),
            'slug'               => uniqid('t-'),
            'sport'              => 'futbol',
            'status'             => $status,
            'visibility'         => 'public',
            'format'             => 'round_robin',
            'points_win'         => 3,
            'points_draw'        => 1,
            'points_loss'        => 0,
            'created_by_user_id' => $admin->id,
        ]);

        $phase = TournamentPhase::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Grupos',
            'type'          => 'groups',
            'status'        => 'active',
            'order'         => 1,
        ]);

        $teamA = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $capA->id,
            'name'            => 'Equipo A',
            'status'          => 'approved',
        ]);
        $teamB = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $capB->id,
            'name'            => 'Equipo B',
            'status'          => 'approved',
        ]);

        return [$tournament, $phase, $teamA, $teamB];
    }

    // ── 1. Dos jugadores en el mismo partido comparten historial ────────────

    public function test_dos_jugadores_en_mismo_partido_aparecen_en_historial_compartido(): void
    {
        $a = $this->makeUser();
        $b = $this->makeUser();
        [$t, $phase, $teamA, $teamB] = $this->makeTournamentWithTeams($a, $b);

        $tpA = TeamPlayer::create(['team_id' => $teamA->id, 'user_id' => $a->id, 'status' => 'active']);
        $tpB = TeamPlayer::create(['team_id' => $teamB->id, 'user_id' => $b->id, 'status' => 'active']);

        $match = TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status'       => 'finished',
            'home_score'   => 1,
            'away_score'   => 0,
            'match_number' => 1,
        ]);

        foreach ([[$tpA, $teamA], [$tpB, $teamB]] as [$tp, $team]) {
            MatchLineup::create([
                'match_id'       => $match->id,
                'team_player_id' => $tp->id,
                'team_id'        => $team->id,
                'started'        => true,
                'minute_in'      => 0,
            ]);
        }

        // a comparte cancha con b (rival), una vez.
        $shared = $this->playedWith()->sharedPlayers($a);
        $this->assertCount(1, $shared);
        $this->assertSame($b->id, $shared->first()->user->id);
        $this->assertSame(1, $shared->first()->shared);

        // Conteo dirigido es simétrico.
        $this->assertSame(1, $this->playedWith()->sharedCount($a, $b));
        $this->assertSame(1, $this->playedWith()->sharedCount($b, $a));
    }

    public function test_amistoso_jugado_cuenta_como_partido_compartido(): void
    {
        $capA = $this->makeUser();
        $capB = $this->makeUser();
        $home = $this->makeClub($capA, 'Local FC');
        $away = $this->makeClub($capB, 'Visita FC');

        // Los capitanes son miembros de su club.
        ClubPlayer::create(['club_id' => $home->id, 'user_id' => $capA->id, 'verification_status' => 'registrado', 'status' => 'active']);
        ClubPlayer::create(['club_id' => $away->id, 'user_id' => $capB->id, 'verification_status' => 'registrado', 'status' => 'active']);

        FriendlyMatch::create([
            'home_club_id'     => $home->id,
            'away_club_id'     => $away->id,
            'status'           => FriendlyMatch::STATUS_JUGADO,
            'result_agreement' => FriendlyMatch::AGREEMENT_ACORDADO,
            'final_home_score' => 2,
            'final_away_score' => 1,
            'scheduled_at'     => now()->subDays(2),
        ]);

        $this->assertSame(1, $this->playedWith()->sharedCount($capA, $capB));
    }

    // ── 2. "Retar" pre-completa la oportunidad ──────────────────────────────

    public function test_retar_precompleta_la_oportunidad(): void
    {
        $captain = $this->makeUser();
        $this->makeClub($captain, 'Mi Club FC'); // necesario para publicar BUSCAR_RIVAL
        $target = $this->makeUser(['name' => 'Rival Buscado', 'play_level' => 'competitivo', 'city' => 'Asunción']);

        // La ficha del jugador ofrece la acción.
        $this->actingAs($captain)
            ->get(route('social.player.show', $target->futgo_id))
            ->assertOk()
            ->assertSee('Retar a un amistoso');

        // El formulario de crear viene pre-completado para retar a ese jugador.
        $response = $this->actingAs($captain)
            ->get(route('social.oportunidades.create', ['tipo' => 'BUSCAR_RIVAL', 'target' => $target->futgo_id]))
            ->assertOk();

        $response->assertSee('Rival Buscado');                 // nombre del destinatario
        $response->assertSee('retando a un amistoso');         // banner de la acción
        $response->assertSee('value="' . $target->id . '"', false); // hidden target_user_id
        $response->assertSee('value="Asunción"', false);       // ciudad pre-completada
    }

    public function test_publicar_con_target_guarda_destinatario_en_payload(): void
    {
        $captain = $this->makeUser();
        $club    = $this->makeClub($captain, 'Mi Club FC');
        $target  = $this->makeUser(['name' => 'Rival Buscado']);

        $this->actingAs($captain)->post(route('social.oportunidades.store'), [
            'type'           => 'BUSCAR_RIVAL',
            'city'           => 'Asunción',
            'required_level' => 'competitivo',
            'club_id'        => $club->id,
            'window_start'   => now()->addDays(3)->format('Y-m-d\TH:i'),
            'target_user_id' => $target->id,
        ])->assertRedirect();

        $op = Opportunity::where('club_id', $club->id)->latest()->first();
        $this->assertNotNull($op);
        $this->assertSame($target->id, $op->payload['directed_to_user_id'] ?? null);
        $this->assertSame('Rival Buscado', $op->payload['directed_to_name'] ?? null);
    }

    // ── 3. La agenda muestra eventos del día en orden cronológico ───────────

    public function test_agenda_muestra_eventos_del_dia_en_orden_cronologico(): void
    {
        $user = $this->makeUser();
        $rivalCap = $this->makeUser();
        [$t, $phase, $teamA, $teamB] = $this->makeTournamentWithTeams($user, $rivalCap);
        TeamPlayer::create(['team_id' => $teamA->id, 'user_id' => $user->id, 'status' => 'active']);

        // Partido de torneo hoy a las 18:00.
        TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status'       => 'scheduled',
            'scheduled_at' => now()->startOfDay()->setTime(18, 0),
            'match_number' => 1,
        ]);

        // Amistoso confirmado hoy a las 10:00 (más temprano).
        $club  = $this->makeClub($user, 'Mi Club FC');
        $rival = $this->makeClub($rivalCap, 'Rival FC');
        ClubPlayer::create(['club_id' => $club->id, 'user_id' => $user->id, 'verification_status' => 'registrado', 'status' => 'active']);
        FriendlyMatch::create([
            'home_club_id' => $club->id,
            'away_club_id' => $rival->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'scheduled_at' => now()->startOfDay()->setTime(10, 0),
        ]);

        $items = $this->agenda()->for($user);

        $this->assertGreaterThanOrEqual(2, $items->count());
        // El amistoso (10:00) precede al partido de torneo (18:00).
        $this->assertSame(SportsAgendaService::KIND_FRIENDLY, $items->first()->kind);

        // Verifica orden cronológico estricto de fechas presentes.
        $dates = $items->pluck('date')->filter()->values();
        $sorted = $dates->sortBy(fn ($d) => $d->getTimestamp())->values();
        $this->assertEquals(
            $sorted->map->getTimestamp()->all(),
            $dates->map->getTimestamp()->all(),
        );
    }

    public function test_agenda_excluye_torneos_y_amistosos_cancelados(): void
    {
        $user = $this->makeUser();
        $rivalCap = $this->makeUser();
        [$t, $phase, $teamA, $teamB] = $this->makeTournamentWithTeams($user, $rivalCap, 'cancelled');
        TeamPlayer::create(['team_id' => $teamA->id, 'user_id' => $user->id, 'status' => 'active']);

        // Partido de un torneo CANCELADO.
        TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status'       => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'match_number' => 1,
        ]);

        // Amistoso CANCELADO.
        $club  = $this->makeClub($user, 'Mi Club FC');
        $rival = $this->makeClub($rivalCap, 'Rival FC');
        FriendlyMatch::create([
            'home_club_id' => $club->id,
            'away_club_id' => $rival->id,
            'status'       => FriendlyMatch::STATUS_CANCELADO,
            'scheduled_at' => now()->addDays(2),
            'cancelled_at' => now(),
        ]);

        $items = $this->agenda()->for($user);

        $this->assertTrue($items->where('kind', SportsAgendaService::KIND_TOURNAMENT_MATCH)->isEmpty());
        $this->assertTrue($items->where('kind', SportsAgendaService::KIND_FRIENDLY)->isEmpty());
    }

    public function test_agenda_convocatoria_pendiente_ofrece_confirmar(): void
    {
        $user = $this->makeUser();
        $rivalCap = $this->makeUser();
        [$t, $phase, $teamA, $teamB] = $this->makeTournamentWithTeams($user, $rivalCap);
        $tp = TeamPlayer::create(['team_id' => $teamA->id, 'user_id' => $user->id, 'status' => 'active']);

        $match = TournamentMatch::create([
            'phase_id'     => $phase->id,
            'home_team_id' => $teamA->id,
            'away_team_id' => $teamB->id,
            'status'       => 'scheduled',
            'scheduled_at' => now()->addDay(),
            'match_number' => 1,
        ]);

        MatchCallUp::create([
            'match_id'       => $match->id,
            'team_player_id' => $tp->id,
            'team_id'        => $teamA->id,
            'status'         => 'convocado',
        ]);

        $this->actingAs($user)->get(route('social.agenda.index'))
            ->assertOk()
            ->assertSee('Estás convocado')
            ->assertSee('Confirmar');
    }

    public function test_agenda_renderiza_recordatorio_amistoso_y_oportunidad(): void
    {
        $user     = $this->makeUser();
        $rivalCap = $this->makeUser();
        $club     = $this->makeClub($user, 'Mi Club FC');
        $rival    = $this->makeClub($rivalCap, 'Rival FC');

        // Amistoso confirmado con fecha pasada → recordatorio de cargar resultado.
        FriendlyMatch::create([
            'home_club_id' => $club->id,
            'away_club_id' => $rival->id,
            'status'       => FriendlyMatch::STATUS_CONFIRMADO,
            'scheduled_at' => now()->subDay(),
        ]);

        // Oportunidad propia próxima a vencer.
        Opportunity::create([
            'type'           => Opportunity::TYPE_BUSCAR_JUGADOR,
            'user_id'        => $user->id,
            'club_id'        => $club->id,
            'city'           => 'Asunción',
            'required_level' => 'competitivo',
            'status'         => Opportunity::STATUS_ABIERTA,
            'expires_at'     => now()->addDays(2),
            'payload'        => ['cupos' => 2],
        ]);

        $this->actingAs($user)->get(route('social.agenda.index'))
            ->assertOk()
            ->assertSee('cargá el resultado')
            ->assertSee('Vence');
    }
}
