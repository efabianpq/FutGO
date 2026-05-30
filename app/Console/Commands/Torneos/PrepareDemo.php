<?php

namespace App\Console\Commands\Torneos;

use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\Standing;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Database\Seeders\DemoTournamentSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Prepara o regenera la demo completa de FutGO.
 *
 * Uso:
 *   php artisan futgo:prepare-demo           → genera/regenera demo
 *   php artisan futgo:prepare-demo --recalc  → solo recalcula standings y stats
 *   php artisan futgo:prepare-demo --check   → valida consistencia sin modificar nada
 */
class PrepareDemo extends Command
{
    protected $signature = 'futgo:prepare-demo
                            {--recalc : Solo recalcular standings y estadísticas sin recrear el torneo}
                            {--check  : Validar consistencia de la demo sin modificar datos}';

    protected $description = 'Prepara la demo completa de FutGO: limpia datos previos, recrea el torneo demo y valida la consistencia.';

    public function handle(
        StandingsCalculatorService $standings,
        PlayerStatsCalculatorService $playerStats
    ): int {
        if ($this->option('check')) {
            return $this->runCheck();
        }

        if ($this->option('recalc')) {
            return $this->runRecalc($standings, $playerStats);
        }

        return $this->runFullPrepare();
    }

    // ─── Modos ──────────────────────────────────────────────────────────────

    private function runFullPrepare(): int
    {
        $this->info('🚀 Preparando demo de FutGO...');
        $this->line('');

        try {
            $seeder = new DemoTournamentSeeder();
            $seeder->setCommand($this);
            $seeder->run();
        } catch (Throwable $e) {
            $this->error('Error generando la demo: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        $this->line('');
        $this->runCheck();

        $this->line('');
        $this->info('✅ Demo lista. URL: http://futgo.test:8080');
        $this->info('   Admin principal:  admin@soypachonmundial.com / Admin2026!');
        $this->info('   Admin torneo:     admin.torneo@demo.futgo.com / Demo2026!');
        $this->info('   Capitán Leones:   ldn.capitan@demo.futgo.com / Demo2026!');

        return self::SUCCESS;
    }

    private function runRecalc(StandingsCalculatorService $standings, PlayerStatsCalculatorService $playerStats): int
    {
        $tournament = $this->findDemoTournament();
        if (! $tournament) {
            $this->error('No se encontró la demo. Ejecutá sin --recalc primero.');
            return self::FAILURE;
        }

        $this->info("Recalculando para: {$tournament->name}");

        $groupPhase = $tournament->phases()->where('type', 'groups')->first();
        if ($groupPhase) {
            $standings->recalculate($groupPhase);
            $this->info('  ✓ Standings recalculados');
        }

        foreach ($tournament->teams()->where('status', 'approved')->get() as $team) {
            $playerStats->recalculate($tournament, $team);
        }
        $this->info('  ✓ Estadísticas de jugadores recalculadas');

        return self::SUCCESS;
    }

    private function runCheck(): int
    {
        $tournament = $this->findDemoTournament();
        if (! $tournament) {
            $this->warn('⚠ No se encontró el torneo demo.');
            return self::FAILURE;
        }

        $phaseIds  = $tournament->phases()->pluck('id');
        $teamCount = $tournament->teams()->where('status', 'approved')->count();
        $playerCount = TeamPlayer::whereIn('team_id',
            $tournament->teams()->pluck('id')
        )->where('status', 'active')->count();
        $matchTotal    = TournamentMatch::whereIn('phase_id', $phaseIds)->count();
        $matchFinished = TournamentMatch::whereIn('phase_id', $phaseIds)->where('status', 'finished')->count();
        $standingCount = Standing::whereIn('phase_id', $phaseIds)->count();
        $statsActive   = PlayerStat::where('tournament_id', $tournament->id)
            ->where('matches_played', '>', 0)->count();

        $checks = [
            ['Torneo demo existe',       true,             '✅'],
            ['Equipos == 8',             $teamCount === 8, $teamCount === 8 ? '✅' : '❌ ' . $teamCount],
            ['Jugadores activos ≥ 80',   $playerCount >= 80, $playerCount >= 80 ? '✅' : '❌ ' . $playerCount],
            ['Partidos generados ≥ 12',  $matchTotal >= 12, $matchTotal >= 12 ? '✅' : '❌ ' . $matchTotal],
            ['Partidos jugados ≥ 6',     $matchFinished >= 6, $matchFinished >= 6 ? '✅' : '❌ ' . $matchFinished],
            ['Standings calculados > 0', $standingCount > 0, $standingCount > 0 ? '✅' : '❌ (0)'],
            ['Stats jugadores > 0',      $statsActive > 0, $statsActive > 0 ? '✅' : '❌ (0)'],
        ];

        // Verificar eliminatorias
        $hasKnockout = $tournament->phases()
            ->where('type', 'knockout')
            ->whereHas('matches', fn ($q) => $q->whereNotNull('home_team_id'))
            ->exists();
        $checks[] = ['Eliminatorias pobladas', $hasKnockout, $hasKnockout ? '✅' : '❌'];

        $this->info('🔍 Validación de consistencia:');
        $this->table(['Check', 'Estado'], collect($checks)->map(fn ($c) => [$c[0], $c[2]])->all());

        $failures = collect($checks)->filter(fn ($c) => $c[1] === false)->count();
        if ($failures > 0) {
            $this->warn("⚠ {$failures} check(s) fallaron. Regenerá con: php artisan futgo:prepare-demo");
            return self::FAILURE;
        }

        $this->info('Todo OK.');
        return self::SUCCESS;
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function findDemoTournament(): ?Tournament
    {
        return Tournament::where('name', 'Copa FutGO Demo 2026')->first();
    }
}
