<?php

namespace Database\Seeders;

use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentGroup;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\User;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de demostración para Copa FutGO Demo 2026.
 *
 * Genera un torneo completamente funcional con:
 *   - 8 equipos (2 grupos × 4), 18 jugadores por equipo (144 total)
 *   - 12 partidos de fase de grupos: 6 finalizados (con eventos, lineups, standings)
 *                                     6 programados a futuro
 *   - Semifinales generadas (fase eliminatoria poblada con clasificados)
 *   - Estadísticas recalculadas: standings, goleadores, asistencias, tarjetas
 *
 * Idempotente: elimina el torneo demo anterior antes de recrearlo.
 */
class DemoTournamentSeeder extends Seeder
{
    private const DEMO_MARKER = 'Copa FutGO Demo 2026';

    // Contraseña única para todos los usuarios demo
    private const DEMO_PASSWORD = 'Demo2026!';

    // Equipos con datos reales inspirados (ficticios)
    private const TEAMS = [
        ['Leones del Norte',  '#C0392B', 'LDN'],
        ['Tigres FC',         '#2980B9', 'TIG'],
        ['Cóndores United',   '#27AE60', 'CDU'],
        ['Halcones Dorados',  '#F39C12', 'HAL'],
        ['Panteras Negras',   '#8E44AD', 'PAN'],
        ['Águilas Imperiales','#16A085', 'AGU'],
        ['Lobos del Sur',     '#E67E22', 'LDS'],
        ['Delfines Azules',   '#2C3E50', 'DEL'],
    ];

    // Plantilla tipo fútbol 11 (se cicla para 18 jugadores)
    private const POSITIONS = [
        'Portero', 'Defensa', 'Defensa', 'Defensa', 'Defensa',
        'Mediocampista', 'Mediocampista', 'Mediocampista', 'Mediocampista',
        'Delantero', 'Delantero',
        'Portero', 'Defensa', 'Defensa', 'Mediocampista',
        'Mediocampista', 'Delantero', 'Delantero',
    ];

    private const PLAYER_FIRST_NAMES = [
        'Carlos','Luis','Miguel','Andrés','Felipe','Sebastián','Jorge','David',
        'Daniel','Alejandro','Ricardo','Fernando','Mateo','Juan','Pedro','Diego',
        'Nicolás','Camilo','Julián','Santiago','Víctor','Eduardo','Roberto','Rafael',
        'Esteban','Hernán','Mauricio','Gabriel','Iván','Cristian',
    ];

    private const PLAYER_LAST_NAMES = [
        'García','Rodríguez','López','Martínez','González','Sánchez','Pérez','Torres',
        'Ramírez','Flores','Rivera','Gómez','Díaz','Herrera','Morales','Jiménez',
        'Ruiz','Vásquez','Medina','Ortiz','Vargas','Castillo','Reyes','Moreno',
        'Silva','Castro','Mendoza','Peña','Rojas','Delgado',
    ];

    // Resultados de los 12 partidos de grupos (6 jugados, 6 futuros)
    // Formato: [home_score, away_score, home_idx, away_idx] (por grupo)
    // Grupo A: equipos 0-3, Grupo B: equipos 4-7
    private const GROUP_RESULTS = [
        // Grupo A — partidos finalizados (match_number 1-6)
        ['score' => [2, 1], 'played' => true],  // A0 vs A1
        ['score' => [0, 0], 'played' => true],  // A2 vs A3
        ['score' => [3, 0], 'played' => true],  // A0 vs A2
        ['score' => [1, 2], 'played' => true],  // A1 vs A3
        // Grupo A — partidos futuros
        ['score' => null,   'played' => false], // A0 vs A3
        ['score' => null,   'played' => false], // A1 vs A2
        // Grupo B — partidos finalizados
        ['score' => [1, 1], 'played' => true],  // B0 vs B1
        ['score' => [2, 0], 'played' => true],  // B2 vs B3
        // Grupo B — partidos futuros
        ['score' => null,   'played' => false], // B0 vs B2
        ['score' => null,   'played' => false], // B1 vs B3
        ['score' => null,   'played' => false], // B0 vs B3
        ['score' => null,   'played' => false], // B1 vs B2
    ];

    public function run(): void
    {
        $this->command?->info('🧹 Eliminando demo anterior...');
        $this->cleanup();

        DB::transaction(function () {
            $this->command?->info('👤 Creando administrador del torneo...');
            $torneoAdmin = $this->createTorneoAdmin();

            $this->command?->info('🏆 Creando torneo Copa FutGO Demo 2026...');
            $tournament = $this->createTournament($torneoAdmin);

            $this->command?->info('⚽ Creando 8 equipos con 18 jugadores cada uno...');
            $teams = $this->createTeams($tournament);

            $this->command?->info('🗓️ Generando fixture (2 grupos × 4 equipos)...');
            app(FixtureGeneratorService::class)->generate($tournament);
            $tournament->refresh();

            $this->command?->info('📅 Asignando fechas y resultados a los partidos...');
            $this->assignMatchResults($tournament, $teams);

            $this->command?->info('🧮 Recalculando standings...');
            $groupPhase = $tournament->phases()->where('type', 'groups')->first();
            app(StandingsCalculatorService::class)->recalculate($groupPhase);

            $this->command?->info('📊 Recalculando estadísticas de jugadores...');
            foreach ($teams as $team) {
                app(PlayerStatsCalculatorService::class)->recalculate($tournament, $team);
            }

            $this->command?->info('🏅 Generando eliminatorias desde standings...');
            $this->advanceToKnockout($tournament, $groupPhase);

            $this->command?->info('✅ Demo generada correctamente.');
            $this->printSummary($tournament, $teams, $torneoAdmin);
        });
    }

    // ─── Cleanup ────────────────────────────────────────────────────────────

    private function cleanup(): void
    {
        $tournament = Tournament::where('name', self::DEMO_MARKER)->first();
        if (! $tournament) {
            return;
        }

        $phaseIds = $tournament->phases()->pluck('id');
        $matchIds = TournamentMatch::whereIn('phase_id', $phaseIds)->pluck('id');
        $teamIds  = $tournament->teams()->pluck('id');

        MatchEvent::whereIn('match_id', $matchIds)->delete();
        MatchLineup::whereIn('match_id', $matchIds)->delete();
        TournamentMatch::whereIn('phase_id', $phaseIds)->delete();
        Standing::whereIn('phase_id', $phaseIds)->delete();
        $groupIds = TournamentGroup::whereIn('phase_id', $phaseIds)->pluck('id');
        DB::table('group_teams')->whereIn('group_id', $groupIds)->delete();
        TournamentGroup::whereIn('phase_id', $phaseIds)->delete();
        TournamentPhase::where('tournament_id', $tournament->id)->delete();

        PlayerStat::where('tournament_id', $tournament->id)->delete();
        $tpIds = TeamPlayer::whereIn('team_id', $teamIds)->pluck('id');
        TeamPlayer::whereIn('team_id', $teamIds)->delete();

        // Borrar usuarios generados por el seeder (no el admin principal)
        $captainIds = $teams = Team::where('tournament_id', $tournament->id)
            ->pluck('captain_user_id');
        Team::where('tournament_id', $tournament->id)->delete();

        DB::table('tournament_admins')->where('tournament_id', $tournament->id)->delete();
        $tournament->delete();
    }

    // ─── Creación ────────────────────────────────────────────────────────────

    private function createTorneoAdmin(): User
    {
        return User::updateOrCreate(
            ['email' => 'admin.torneo@demo.futgo.com'],
            [
                'name'              => 'Admin Copa Demo',
                'password'          => Hash::make(self::DEMO_PASSWORD),
                'role'              => 'torneo_admin',
                'modules'           => 'torneos',
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );
    }

    private function createTournament(User $admin): Tournament
    {
        $tournament = Tournament::create([
            'name'                 => self::DEMO_MARKER,
            'slug'                 => 'copa-futgo-demo-2026',
            'sport'                => 'futbol',
            'status'               => 'open',
            'format'               => 'groups_and_knockout',
            'groups_count'         => 2,
            'teams_per_group'      => 4,
            'classifies_per_group' => 2,
            'max_teams'            => 8,
            'third_place_match'    => true,
            'points_win'           => 3,
            'points_draw'          => 1,
            'points_loss'          => 0,
            'tiebreaker_order'     => ['goal_difference', 'goals_for', 'head_to_head'],
            'knockout_tiebreak'    => 'penalties',
            'match_duration'       => 90,
            'max_substitutions'    => 5,
            'min_players_per_team' => 11,
            'max_players_per_team' => 25,
            'registration_fee'     => 0,
            'visibility'           => 'public',
            'category'             => 'libre',
            'city'                 => 'Bogotá',
            'venue'                => 'Estadio FutGO',
            'starts_at'            => Carbon::now()->subDays(10),
            'ends_at'              => Carbon::now()->addDays(20),
            'description'          => 'Torneo demostrativo de FutGO — Copa 2026.',
            'created_by_user_id'   => $admin->id,
        ]);

        $tournament->tournamentAdmins()->create(['user_id' => $admin->id]);

        return $tournament;
    }

    /** @return array<int, Team> */
    private function createTeams(Tournament $tournament): array
    {
        $teams = [];
        $firstNameIdx = 0;
        $lastNameIdx  = 0;

        foreach (self::TEAMS as $teamIdx => [$teamName, $color, $prefix]) {
            // Email del capitán único por equipo
            $captainEmail = strtolower($prefix) . '.capitan@demo.futgo.com';
            $captain = User::updateOrCreate(
                ['email' => $captainEmail],
                [
                    'name'              => 'Cap. ' . $teamName,
                    'password'          => Hash::make(self::DEMO_PASSWORD),
                    'role'              => 'user',
                    'modules'           => 'torneos',
                    'is_active'         => true,
                    'email_verified_at' => now(),
                ]
            );

            $team = Team::create([
                'tournament_id'   => $tournament->id,
                'captain_user_id' => $captain->id,
                'name'            => $teamName,
                'color'           => $color,
                'status'          => 'approved',
            ]);

            // Capitán como primer jugador
            TeamPlayer::create([
                'team_id'        => $team->id,
                'user_id'        => $captain->id,
                'jersey_number'  => '10',
                'position'       => 'Mediocampista',
                'status'         => 'active',
            ]);

            // 17 jugadores adicionales (total 18)
            for ($p = 1; $p <= 17; $p++) {
                $firstName = self::PLAYER_FIRST_NAMES[$firstNameIdx % count(self::PLAYER_FIRST_NAMES)];
                $lastName  = self::PLAYER_LAST_NAMES[$lastNameIdx % count(self::PLAYER_LAST_NAMES)];
                $firstNameIdx++;
                $lastNameIdx += 3;

                $playerEmail = strtolower($prefix) . '.j' . $p . '@demo.futgo.com';

                $playerUser = User::updateOrCreate(
                    ['email' => $playerEmail],
                    [
                        'name'              => "{$firstName} {$lastName}",
                        'password'          => Hash::make(self::DEMO_PASSWORD),
                        'role'              => 'user',
                        'modules'           => 'torneos',
                        'is_active'         => true,
                        'email_verified_at' => now(),
                    ]
                );

                TeamPlayer::create([
                    'team_id'        => $team->id,
                    'user_id'        => $playerUser->id,
                    'jersey_number'  => (string) ($p + 1),
                    'position'       => self::POSITIONS[$p % count(self::POSITIONS)],
                    'status'         => 'active',
                ]);
            }

            $teams[] = $team->fresh();
        }

        return $teams;
    }

    // ─── Resultados ──────────────────────────────────────────────────────────

    /**
     * Asigna resultados, lineups y eventos a los partidos de grupos.
     * La distribución sigue GROUP_RESULTS: los partidos se asignan en el orden
     * en que el generador los crea (round-robin: equipo i vs equipo j, i<j).
     *
     * @param array<int, Team> $teams
     */
    private function assignMatchResults(Tournament $tournament, array $teams): void
    {
        $groupPhase = $tournament->phases()->where('type', 'groups')->orderBy('order')->first();
        $groups = $groupPhase->groups()->orderBy('order')->get();

        // Los partidos de cada grupo en orden de match_number
        $matchByGroup = [];
        foreach ($groups as $gIdx => $group) {
            $matchByGroup[$gIdx] = TournamentMatch::where('phase_id', $groupPhase->id)
                ->where('group_id', $group->id)
                ->orderBy('match_number')
                ->get();
        }

        // Resultados predefinidos para cada grupo
        $resultsByGroup = [
            0 => [ // Grupo A: 6 partidos, 4 jugados
                ['score' => [2, 1], 'played' => true],
                ['score' => [0, 0], 'played' => true],
                ['score' => [3, 0], 'played' => true],
                ['score' => [1, 2], 'played' => true],
                ['score' => null,   'played' => false],
                ['score' => null,   'played' => false],
            ],
            1 => [ // Grupo B: 6 partidos, 2 jugados
                ['score' => [1, 1], 'played' => true],
                ['score' => [2, 0], 'played' => true],
                ['score' => null,   'played' => false],
                ['score' => null,   'played' => false],
                ['score' => null,   'played' => false],
                ['score' => null,   'played' => false],
            ],
        ];

        $futureBase = Carbon::now()->addDays(2)->setHour(16)->setMinute(0)->setSecond(0);

        foreach ($groups as $gIdx => $group) {
            $matches = $matchByGroup[$gIdx];
            $results = $resultsByGroup[$gIdx] ?? [];

            // Equipos del grupo para extraer jugadores para lineups/eventos
            $groupTeamIds = $group->teams()->orderBy('teams.id')->pluck('teams.id')->all();
            $groupTeams   = Team::whereIn('id', $groupTeamIds)->with('players')->get()->keyBy('id');

            foreach ($matches as $mIdx => $match) {
                $result  = $results[$mIdx] ?? ['score' => null, 'played' => false];

                if (! $result['played']) {
                    // Partido futuro: asignar fecha programada
                    $match->update(['scheduled_at' => $futureBase->copy()->addHours($mIdx * 2)]);
                    continue;
                }

                [$homeScore, $awayScore] = $result['score'];
                $winnerId = match (true) {
                    $homeScore > $awayScore => $match->home_team_id,
                    $awayScore > $homeScore => $match->away_team_id,
                    default                 => null,
                };

                $match->home_score     = $homeScore;
                $match->away_score     = $awayScore;
                $match->winner_team_id = $winnerId;
                $match->status         = 'finished';
                $match->scheduled_at   = Carbon::now()->subDays(7 - $mIdx)->setHour(15);
                $match->save();

                // Lineups: todos los jugadores activos del partido (solo los titulares para simplificar)
                $homeTeam = $groupTeams[$match->home_team_id] ?? null;
                $awayTeam = $groupTeams[$match->away_team_id] ?? null;

                if ($homeTeam) {
                    $this->createLineups($match, $homeTeam, $match->home_team_id);
                }
                if ($awayTeam) {
                    $this->createLineups($match, $awayTeam, $match->away_team_id);
                }

                // Eventos: goles coherentes con el marcador
                if ($homeTeam && $homeScore > 0) {
                    $this->createGoalEvents($match, $homeTeam, $homeScore, false);
                }
                if ($awayTeam && $awayScore > 0) {
                    $this->createGoalEvents($match, $awayTeam, $awayScore, false);
                }

                // 1 tarjeta amarilla por equipo en partidos disputados
                if ($homeTeam) {
                    $this->createCardEvent($match, $homeTeam, 'yellow_card');
                }
            }
        }
    }

    private function createLineups(TournamentMatch $match, Team $team, int $teamId): void
    {
        // 11 titulares de los primeros 11 jugadores del equipo
        $players = $team->players()->where('status', 'active')->orderBy('jersey_number')->limit(11)->get();
        foreach ($players as $tp) {
            MatchLineup::create([
                'match_id'       => $match->id,
                'team_player_id' => $tp->id,
                'team_id'        => $teamId,
                'started'        => true,
                'minute_in'      => 0,
                'minute_out'     => null,
            ]);
        }
    }

    private function createGoalEvents(TournamentMatch $match, Team $team, int $goals, bool $ownGoal = false): void
    {
        // Los delanteros marcan (posición Delantero) o el capitán si no hay
        $scorers = $team->players()
            ->where('status', 'active')
            ->where('position', 'LIKE', '%Delantero%')
            ->orderBy('jersey_number')
            ->get();

        if ($scorers->isEmpty()) {
            $scorers = $team->players()->where('status', 'active')->limit(3)->get();
        }

        for ($g = 0; $g < $goals; $g++) {
            $scorer = $scorers[$g % $scorers->count()];
            MatchEvent::create([
                'match_id'       => $match->id,
                'team_player_id' => $scorer->id,
                'type'           => 'goal',
                'minute'         => 15 + ($g * 20) % 70,
            ]);

            // Asistencia al gol si hay un segundo jugador disponible
            if ($scorers->count() > 1) {
                $assister = $scorers[($g + 1) % $scorers->count()];
                if ($assister->id !== $scorer->id) {
                    MatchEvent::create([
                        'match_id'       => $match->id,
                        'team_player_id' => $assister->id,
                        'type'           => 'assist',
                        'minute'         => 15 + ($g * 20) % 70,
                    ]);
                }
            }
        }
    }

    private function createCardEvent(TournamentMatch $match, Team $team, string $type): void
    {
        $player = $team->players()
            ->where('status', 'active')
            ->where('position', 'LIKE', '%Defensa%')
            ->first();

        if (! $player) {
            $player = $team->players()->where('status', 'active')->skip(2)->first();
        }

        if ($player) {
            MatchEvent::create([
                'match_id'       => $match->id,
                'team_player_id' => $player->id,
                'type'           => $type,
                'minute'         => 45,
            ]);
        }
    }

    // ─── Eliminatorias ───────────────────────────────────────────────────────

    private function advanceToKnockout(Tournament $tournament, TournamentPhase $groupPhase): void
    {
        // Solo pobla si el knockout placeholder existe y hay standings calculados
        $nextPhase = $tournament->phases()
            ->where('type', 'knockout')
            ->where('order', '>', $groupPhase->order)
            ->orderBy('order')
            ->first();

        if (! $nextPhase) {
            return;
        }

        // Verificar que hay standings para avanzar
        if (Standing::where('phase_id', $groupPhase->id)->count() === 0) {
            return;
        }

        try {
            app(FixtureGeneratorService::class)->advanceTeams($groupPhase);
        } catch (\Throwable $e) {
            $this->command?->warn('No se pudieron generar eliminatorias: ' . $e->getMessage());
        }
    }

    // ─── Resumen ─────────────────────────────────────────────────────────────

    /**
     * @param array<int, Team> $teams
     */
    private function printSummary(Tournament $tournament, array $teams, User $torneoAdmin): void
    {
        $phaseIds = $tournament->phases()->pluck('id');
        $matchTotal    = TournamentMatch::whereIn('phase_id', $phaseIds)->count();
        $matchFinished = TournamentMatch::whereIn('phase_id', $phaseIds)->where('status', 'finished')->count();
        $playerCount   = TeamPlayer::whereIn('team_id', collect($teams)->pluck('id'))->count();
        $standingCount = Standing::whereIn('phase_id', $phaseIds)->count();
        $statsCount    = PlayerStat::where('tournament_id', $tournament->id)->where('matches_played', '>', 0)->count();

        $this->command?->table(
            ['Elemento', 'Cantidad'],
            [
                ['Torneos demo',       1],
                ['Equipos',            count($teams)],
                ['Jugadores totales',  $playerCount],
                ['Partidos generados', $matchTotal],
                ['Partidos jugados',   $matchFinished],
                ['Standings',          $standingCount],
                ['Stats calculadas',   $statsCount],
            ]
        );

        $this->command?->line('');
        $this->command?->info('📋 Inventario de usuarios demo:');
        $this->command?->table(
            ['Rol', 'Nombre', 'Email', 'Contraseña'],
            [
                ['Admin principal',      'Admin SoyPachon',    'admin@soypachonmundial.com',    'Admin2026!'],
                ['Admin torneo',         $torneoAdmin->name,   $torneoAdmin->email,             self::DEMO_PASSWORD],
                ['Capitán (Leones)',     'Cap. Leones del Norte', 'ldn.capitan@demo.futgo.com', self::DEMO_PASSWORD],
                ['Capitán (Tigres)',     'Cap. Tigres FC',     'tig.capitan@demo.futgo.com',   self::DEMO_PASSWORD],
                ['Jugador (Leones #2)',  'Ver BD',             'ldn.j1@demo.futgo.com',        self::DEMO_PASSWORD],
            ]
        );
    }
}
