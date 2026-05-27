<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Services\PredictionsCalculator;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Demo maestro — datos completos para videos publicitarios y presentaciones.
 *
 * Idempotente: cada subseeder usa updateOrInsert. El cleanup borra cualquier
 * predicción/resultado previo en matches 1..45 para garantizar estado consistente.
 *
 * Ejecutar:
 *   php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🧹 Limpiando datos previos en matches 1..45...');
        $this->cleanup();

        $this->command->info('👥 Insertando 10 usuarios demo + códigos de invitación usados...');
        $this->call(DemoUsersSeeder::class);

        $this->command->info('⚽ Marcando los 15 primeros partidos como finalizados con resultados reales...');
        $this->call(DemoMatchesSeeder::class);

        $this->command->info('📝 Generando predictions con distribución intencional (150 finished + 200 locked)...');
        $this->call(DemoPredictionsSeeder::class);

        $this->command->info('💰 Configurando acumulado en COP $500.000...');
        Settings::setPrizePool(500000);
        Settings::set(Settings::TOURNAMENT_NAME, '@SoyPachonMundial 2026');
        Settings::set(Settings::WELCOME_MESSAGE, 'Demo en vivo — 10 participantes pronosticando los 104 partidos del Mundial 2026. ¡Suscribíte para no quedarte por fuera!');

        $this->command->info('🧮 Calculando puntos de los 15 partidos finalizados + recalculando ranking...');
        $calculator = app(PredictionsCalculator::class);
        $games = Game::whereBetween('match_number', [1, 15])
            ->where('status', 'finished')
            ->orderBy('match_number')
            ->get();

        foreach ($games as $g) {
            $r = $calculator->calculate($g);
            $d = $r['distribution'];
            $this->command->line(sprintf(
                "  ✓ Partido #%-2d %s %d-%d %s   →   5pts:%d  3pts:%d  2pts:%d  1pt:%d  0pts:%d",
                $g->match_number,
                $g->home_team,
                $g->home_score_official,
                $g->away_score_official,
                $g->away_team,
                $d[5], $d[3], $d[2], $d[1], $d[0]
            ));
        }

        $this->command->line('');
        $this->printRanking();
        $this->command->line('');
        $this->printPredictionStates();
        $this->command->line('');
        $this->command->info('🎬 Demo listo. Iniciá sesión con cualquier usuario @demo.com / Demo2026!');
    }

    private function cleanup(): void
    {
        // Predictions + match_notifications de los matches 1..45
        $ids = DB::table('matches')->whereBetween('match_number', [1, 45])->pluck('id');
        DB::table('predictions')->whereIn('match_id', $ids)->delete();
        DB::table('match_notifications')->whereIn('match_id', $ids)->delete();

        // Reset matches 1..45 a 'upcoming' sin resultado (DemoMatchesSeeder volverá a marcar 1..15)
        DB::table('matches')->whereIn('id', $ids)->update([
            'status' => 'upcoming',
            'home_score_official' => null,
            'away_score_official' => null,
        ]);

        // Limpiar puntos parciales en rankings
        DB::table('rankings')->update([
            'total_points' => 0,
            'exact_predictions' => 0,
            'current_position' => null,
            'previous_position' => null,
            'last_calculated_at' => null,
        ]);
    }

    private function printRanking(): void
    {
        $rows = DB::table('rankings')
            ->join('users', 'users.id', '=', 'rankings.user_id')
            ->where('users.is_active', true)
            ->where('users.role', 'user')
            ->orderBy('rankings.current_position')
            ->get([
                'rankings.current_position as pos',
                'users.name',
                'rankings.total_points as pts',
                'rankings.exact_predictions as exactos',
            ]);

        $this->command->info('🏆 RANKING FINAL');
        $this->command->getOutput()->table(
            ['Pos', 'Participante', 'Puntos', 'Exactos'],
            $rows->map(fn ($r) => [
                $r->pos ?? '—',
                $r->name,
                $r->pts,
                $r->exactos,
            ])->all()
        );
    }

    private function printPredictionStates(): void
    {
        $finishedMatchIds = DB::table('matches')
            ->whereBetween('match_number', [1, 15])
            ->pluck('id');

        $lockedMatchIds = DB::table('matches')
            ->whereBetween('match_number', [16, 35])
            ->pluck('id');

        $openMatchIds = DB::table('matches')
            ->whereBetween('match_number', [36, 45])
            ->pluck('id');

        $calculated = DB::table('predictions')->whereIn('match_id', $finishedMatchIds)->count();
        $locked = DB::table('predictions')->whereIn('match_id', $lockedMatchIds)->count();
        $openPredictions = DB::table('predictions')->whereIn('match_id', $openMatchIds)->count();

        $this->command->info('📊 ESTADO DE PRONÓSTICOS');
        $this->command->getOutput()->table(
            ['Grupo de partidos', 'Partidos', 'Predictions', 'Estado'],
            [
                ['#1..15 (finalizados)', $finishedMatchIds->count(), $calculated, '✅ calculados'],
                ['#16..35 (próximos)',   $lockedMatchIds->count(),   $locked,     '🔒 bloqueados'],
                ['#36..45 (abiertos)',   $openMatchIds->count(),     $openPredictions, '🟢 sin pronóstico'],
            ]
        );
    }
}
