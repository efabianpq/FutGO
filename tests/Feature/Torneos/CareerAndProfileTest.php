<?php

namespace Tests\Feature\Torneos;

use App\Models\Torneos\Club;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Services\Torneos\PlayerCareerStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Sesión B — Perfil permanente del jugador/club + foto de perfil.
 */
class CareerAndProfileTest extends TestCase
{
    use RefreshDatabase;

    private function torneoUser(): User
    {
        return User::factory()->create([
            'is_active' => true, 'role' => 'user', 'modules' => 'torneos',
        ]);
    }

    private function makeTournament(User $admin, string $status = 'in_progress'): Tournament
    {
        $t = Tournament::create([
            'name'                 => 'Copa ' . uniqid(),
            'slug'                 => 'copa-' . uniqid(),
            'sport'                => 'futbol',
            'status'               => $status,
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            'created_by_user_id'   => $admin->id,
        ]);
        $t->admins()->attach($admin->id);
        return $t;
    }

    /** Crea un equipo (con club) en un torneo con un jugador y sus player_stats. */
    private function playerInTournament(User $player, Tournament $t, array $stats, string $teamName = null, Club $club = null): TeamPlayer
    {
        $teamName ??= 'Equipo ' . uniqid();
        $club ??= Club::create([
            'name' => $teamName, 'slug' => 'club-' . uniqid(), 'created_by_user_id' => $player->id,
        ]);

        $team = Team::create([
            'tournament_id'   => $t->id,
            'club_id'         => $club->id,
            'captain_user_id' => $player->id,
            'name'            => $teamName,
            'status'          => 'approved',
        ]);

        $tp = TeamPlayer::create([
            'team_id' => $team->id, 'user_id' => $player->id, 'status' => 'active',
        ]);

        PlayerStat::create(array_merge([
            'tournament_id'  => $t->id,
            'team_player_id' => $tp->id,
            'goals' => 0, 'assists' => 0, 'yellow_cards' => 0, 'red_cards' => 0,
            'minutes_played' => 0, 'matches_played' => 0,
            'wins' => 0, 'draws' => 0, 'losses' => 0, 'clean_sheets' => 0, 'mvps' => 0,
        ], $stats));

        return $tp;
    }

    // ─── 1. Acumulado suma 2+ torneos ─────────────────────────────────────────

    public function test_acumulado_suma_stats_de_dos_torneos(): void
    {
        $admin  = $this->torneoUser();
        $player = $this->torneoUser();

        $t1 = $this->makeTournament($admin);
        $t2 = $this->makeTournament($admin);

        $this->playerInTournament($player, $t1, [
            'goals' => 5, 'assists' => 2, 'matches_played' => 6, 'minutes_played' => 540, 'mvps' => 1, 'wins' => 4,
        ]);
        $this->playerInTournament($player, $t2, [
            'goals' => 3, 'assists' => 4, 'matches_played' => 4, 'minutes_played' => 360, 'mvps' => 2, 'wins' => 2,
        ]);

        $career = app(PlayerCareerStatsService::class)->refreshForUser($player);

        $this->assertEquals(8, $career->goals);
        $this->assertEquals(6, $career->assists);
        $this->assertEquals(10, $career->matches_played);
        $this->assertEquals(900, $career->minutes_played);
        $this->assertEquals(3, $career->mvps);
        $this->assertEquals(6, $career->wins);
        $this->assertEquals(2, $career->tournaments_count);
        $this->assertEquals(2, $career->teams_count);
    }

    // ─── 2. Foto de perfil: sube, guarda y recupera ───────────────────────────

    public function test_foto_de_perfil_se_sube_y_se_recupera(): void
    {
        Storage::fake('public');
        $user = $this->torneoUser();

        $response = $this->actingAs($user)->post(route('profile.photo'), [
            'avatar' => UploadedFile::fake()->image('foto.jpg', 300, 300),
        ]);

        $response->assertRedirect();
        $user->refresh();

        $this->assertNotNull($user->avatar_url);
        $this->assertStringContainsString('/storage/avatars/', $user->avatar_url);

        // El archivo quedó guardado en el disco público.
        $stored = Storage::disk('public')->allFiles('avatars');
        $this->assertNotEmpty($stored);

        // Se muestra en el perfil.
        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee($user->avatar_url);
    }

    public function test_validacion_de_imagen_rechaza_archivos_invalidos(): void
    {
        Storage::fake('public');
        $user = $this->torneoUser();

        // Tipo inválido (PDF).
        $this->actingAs($user)->post(route('profile.photo'), [
            'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('avatar');

        // Tamaño excesivo (> 2 MB).
        $this->actingAs($user)->post(route('profile.photo'), [
            'avatar' => UploadedFile::fake()->image('grande.jpg')->size(3000),
        ])->assertSessionHasErrors('avatar');

        $user->refresh();
        $this->assertNull($user->avatar_url);
    }

    // ─── 3. Perfil del club muestra historial de torneos ──────────────────────

    public function test_perfil_de_club_muestra_historial_de_torneos(): void
    {
        $admin  = $this->torneoUser();
        $player = $this->torneoUser();

        $club = Club::create(['name' => 'Halcones FC', 'slug' => 'halcones-fc', 'created_by_user_id' => $player->id]);

        $t1 = $this->makeTournament($admin);
        $t1->update(['name' => 'Liga Verano']);
        $t2 = $this->makeTournament($admin);
        $t2->update(['name' => 'Liga Invierno']);

        $this->playerInTournament($player, $t1, ['goals' => 2, 'matches_played' => 3], 'Halcones FC', $club);
        $this->playerInTournament($player, $t2, ['goals' => 1, 'matches_played' => 2], 'Halcones FC', $club);

        $this->actingAs($player)->get(route('torneos.clubes.show', $club))
            ->assertOk()
            ->assertSee('Halcones FC')
            ->assertSee('Liga Verano')
            ->assertSee('Liga Invierno');
    }

    // ─── 4. Finalizar torneo consolida y conserva el histórico ────────────────

    public function test_finalizar_torneo_conserva_y_consolida_historico(): void
    {
        $admin  = User::factory()->create([
            'is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos',
        ]);
        $player = $this->torneoUser();

        $t = $this->makeTournament($admin, 'in_progress');
        $this->playerInTournament($player, $t, ['goals' => 4, 'matches_played' => 5]);

        // Transición in_progress → finished (vía HTTP, dispara la consolidación).
        $this->actingAs($admin)
            ->patch(route('admin.torneos.status', $t), ['status' => 'finished'])
            ->assertRedirect();

        $this->assertEquals('finished', $t->fresh()->status);

        // Las player_stats NO se borraron al finalizar.
        $this->assertDatabaseHas('player_stats', ['tournament_id' => $t->id, 'goals' => 4]);

        // El acumulado histórico quedó consolidado.
        $career = PlayerCareerStat::where('user_id', $player->id)->first();
        $this->assertNotNull($career);
        $this->assertEquals(4, $career->goals);
        $this->assertEquals(5, $career->matches_played);
    }

    // ─── 5. Jugador en dos equipos distintos ve ambos en su historial ─────────

    public function test_jugador_en_dos_equipos_ve_ambos_en_su_carrera(): void
    {
        $admin  = $this->torneoUser();
        $player = $this->torneoUser();

        $t1 = $this->makeTournament($admin);
        $t2 = $this->makeTournament($admin);

        $this->playerInTournament($player, $t1, ['goals' => 1, 'matches_played' => 2], 'Tigres FC');
        $this->playerInTournament($player, $t2, ['goals' => 2, 'matches_played' => 3], 'Leones FC');

        $this->actingAs($player)->get(route('torneos.mi-carrera'))
            ->assertOk()
            ->assertSee('Tigres FC')
            ->assertSee('Leones FC');
    }

    // ─── Un equipo permanente participa en varios torneos (identidad única) ────

    public function test_equipo_permanente_se_enrola_en_dos_torneos(): void
    {
        $admin   = User::factory()->create(['is_active' => true, 'role' => 'torneo_admin', 'modules' => 'torneos']);
        $captain = $this->torneoUser();

        // Crear equipo permanente (vía HTTP: el creador queda capitán).
        $this->actingAs($captain)->post(route('torneos.equipos.store'), ['name' => 'Dragones'])->assertRedirect();
        $club = Club::where('captain_user_id', $captain->id)->where('name', 'Dragones')->firstOrFail();

        $t1 = $this->makeTournament($admin, 'open');
        $t2 = $this->makeTournament($admin, 'open');

        $this->actingAs($captain)->post(route('torneos.equipo.store', $t1), ['club_id' => $club->id])->assertRedirect();
        $this->actingAs($captain)->post(route('torneos.equipo.store', $t2), ['club_id' => $club->id])->assertRedirect();

        // El mismo club permanente, con dos inscripciones (teams).
        $this->assertEquals(2, $club->teams()->count());
        $this->assertEquals(1, Club::where('captain_user_id', $captain->id)->where('name', 'Dragones')->count());
    }
}
