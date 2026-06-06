<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            ['is_active' => true, 'modules' => 'torneos'],
            $attrs
        ));
    }

    /**
     * Torneo round_robin con fixture generado.
     *
     * @return array{0:Tournament,1:\Illuminate\Support\Collection}
     */
    private function makeScenario(int $n = 4): array
    {
        $admin = $this->makeUser(['role' => 'torneo_admin']);

        $tournament = Tournament::create([
            'name'                 => 'Liga ' . uniqid(),
            'slug'                 => 'liga-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'round_robin',
            'groups_count'         => 1,
            'teams_per_group'      => $n,
            'classifies_per_group' => 1,
            'max_teams'            => $n,
            'third_place_match'    => false,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'match_duration'       => 90,
            'category'             => 'libre',
            'visibility'           => 'public',
            'created_by_user_id'   => $admin->id,
        ]);
        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        $teams = collect();
        for ($i = 0; $i < $n; $i++) {
            $cap  = $this->makeUser(['name' => "Capitan{$i}"]);
            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $cap->id,
                'name'            => "Equipo{$i}",
                'status'          => 'approved',
            ]);
            TeamPlayer::create(['team_id' => $team->id, 'user_id' => $cap->id, 'status' => 'active']);
            $teams->push($team);
        }

        app(FixtureGeneratorService::class)->generate($tournament);
        $tournament->refresh();

        return [$tournament, $teams];
    }

    private function playerOf(Team $team): User
    {
        return User::find($team->captain_user_id);
    }

    // ─── Acceso ────────────────────────────────────────────────────────────────

    public function test_jugador_accede_a_mi_actividad(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        $this->actingAs($this->playerOf($teams[0]))
            ->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee('Hoja de vida deportiva');
    }

    public function test_mi_actividad_muestra_torneos_del_jugador(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        $this->actingAs($this->playerOf($teams[0]))
            ->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee($tournament->name);
    }

    public function test_mi_actividad_muestra_estadisticas_agregadas(): void
    {
        [$tournament, $teams] = $this->makeScenario();
        $player       = $this->playerOf($teams[0]);
        $teamPlayer   = TeamPlayer::where('team_id', $teams[0]->id)->where('user_id', $player->id)->first();

        PlayerStat::create([
            'tournament_id'  => $tournament->id,
            'team_player_id' => $teamPlayer->id,
            'goals'          => 5,
            'assists'        => 2,
            'matches_played' => 3,
        ]);

        $this->actingAs($player)
            ->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee('Goles')
            ->assertSee('5');
    }

    public function test_mi_actividad_muestra_sanciones_y_disciplina(): void
    {
        [$tournament, $teams] = $this->makeScenario();
        $player     = $this->playerOf($teams[0]);
        $teamPlayer = TeamPlayer::where('team_id', $teams[0]->id)->where('user_id', $player->id)->first();

        // Partido del equipo 0 → roja → jugador suspendido (inactive).
        $match = TournamentMatch::whereHas('phase', fn ($q) => $q->where('tournament_id', $tournament->id))
            ->where(fn ($q) => $q->where('home_team_id', $teams[0]->id)->orWhere('away_team_id', $teams[0]->id))
            ->first();

        MatchEvent::create([
            'match_id'       => $match->id,
            'team_player_id' => $teamPlayer->id,
            'type'           => 'red_card',
            'minute'         => 70,
        ]);
        $teamPlayer->update(['status' => 'inactive']);

        $this->actingAs($player)
            ->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee('Disciplina')
            ->assertSee('suspensión');
    }

    // ─── Autorización / menú ────────────────────────────────────────────────────

    public function test_usuario_sin_modulo_torneos_es_redirigido(): void
    {
        $pollaUser = $this->makeUser(['modules' => 'polla']);

        $this->actingAs($pollaUser)
            ->get(route('torneos.mi-carrera'))
            ->assertRedirect(route('predictions.index'));
    }

    public function test_navbar_jugador_muestra_mi_actividad_y_oculta_gestion(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        $this->actingAs($this->playerOf($teams[0]))
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Mi Carrera')
            ->assertDontSee('Gestión Torneos');
    }

    // ─── H16: credencial como modal en Mi Carrera ───────────────────────────────

    public function test_mi_carrera_incluye_credencial_como_modal_con_qr(): void
    {
        [$tournament, $teams] = $this->makeScenario();
        $player = $this->playerOf($teams[0]);

        $this->actingAs($player)
            ->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee('Mi credencial')          // botón que abre el modal
            ->assertSee('credentialOpen', false)  // estado Alpine del modal
            ->assertSee($player->futgo_id)        // identificador FUTGO en el modal
            ->assertSee('<svg', false);           // QR renderizado como SVG
    }

    public function test_credencial_ya_no_esta_en_el_nav(): void
    {
        [$tournament, $teams] = $this->makeScenario();

        // H16: "Mi Credencial" dejó de ser un ítem de menú (ahora es modal).
        $this->actingAs($this->playerOf($teams[0]))
            ->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('Mi Credencial');
    }
}
