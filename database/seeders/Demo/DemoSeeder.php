<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestador del MUNDO DEMO de FutGO.
 *
 * Construye una comunidad coherente de fútbol amateur colombiano: equipos
 * permanentes, 5 torneos en todos los estados, reputación consolidada con los
 * servicios reales y toda la capa social (oportunidades, amistosos, mensajería,
 * feed, confiabilidad). Al final ejecuta un autodiagnóstico de cobertura.
 *
 * Correr con:  php artisan migrate:fresh --seed   (en local / DEMO_DATA=true)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->line('');
        $this->command?->info('🌎 Sembrando el mundo FutGO (demo)...');

        $this->command?->info('👥 Bloque 1 — Personas y equipos permanentes');
        $this->call(DemoUsersAndClubsSeeder::class);

        $this->command?->info('🏆 Bloque 2 — Torneos (todos los estados)');
        $this->command?->info('🌱 Sembrando Torneo 1 — Liga Recreativa Bucaramanga 2025...');
        $this->call(DemoTorneo1Seeder::class);
        $this->command?->info('🌱 Sembrando Torneo 2 — Copa Élite Santander 2026...');
        $this->call(DemoTorneo2Seeder::class);
        $this->command?->info('🌱 Sembrando Torneo 3 — Torneo Relámpago Nocturno...');
        $this->call(DemoTorneo3Seeder::class);
        $this->command?->info('🌱 Sembrando Torneo 4 — Torneo Empresarial Café 2026...');
        $this->call(DemoTorneo4Seeder::class);
        $this->command?->info('🌱 Sembrando Torneo 5 — Torneo Privado Club Ejecutivos...');
        $this->call(DemoTorneo5Seeder::class);

        $this->command?->info('📊 Bloque 4 — Reputación y datos transversales');
        $this->call(DemoReputacionSeeder::class);

        $this->command?->info('📣 Bloque 3 — FutGO Social');
        $this->call(DemoSocialSeeder::class);

        $this->printSummary();
        $this->diagnoseGaps();
    }

    // ── Resumen numérico ────────────────────────────────────────────────────

    private function printSummary(): void
    {
        $rows = [
            ['Usuarios',                $this->count('users')],
            ['Equipos permanentes',     $this->count('clubs')],
            ['Plantilla (club_players)', $this->count('club_players')],
            ['Torneos',                 $this->count('tournaments')],
            ['Partidos de torneo',      $this->count('tournament_matches')],
            ['Eventos de partido',      $this->count('match_events')],
            ['Convocatorias',           $this->count('match_call_ups')],
            ['Standings',               $this->count('standings')],
            ['Logros otorgados',        $this->count('user_achievements')],
            ['Ranking (filas)',         $this->count('futgo_rankings')],
            ['Validaciones credencial', $this->count('credential_validations')],
            ['Oportunidades',           $this->count('opportunities')],
            ['Amistosos',               $this->count('friendly_matches')],
            ['Conversaciones',          $this->count('conversations')],
            ['Mensajes',                $this->count('messages')],
            ['Seguimientos',            $this->count('follows')],
            ['Eventos de confiabilidad', $this->count('reliability_events')],
            ['Eventos de feed',         $this->count('feed_events')],
            ['Canchas',                 $this->count('venues')],
        ];

        $this->command?->line('');
        $this->command?->info('📦 Resumen del mundo sembrado:');
        $this->command?->table(['Elemento', 'Cantidad'], $rows);
    }

    // ── Autodiagnóstico de cobertura (Bloque 5) ──────────────────────────────

    /**
     * Revisa que el mundo cubra todas las funcionalidades esperadas. Lista en
     * consola lo que falte (no lanza excepción: es informativo).
     */
    private function diagnoseGaps(): void
    {
        $gaps = [];
        $check = function (string $label, bool $ok) use (&$gaps) {
            if (! $ok) {
                $gaps[] = $label;
            }
        };

        // Mundo base.
        $check('Usuarios (>=30)', $this->count('users') >= 30);
        $check('10 equipos permanentes', $this->count('clubs') >= 10);
        $check('Jugadores por_verificar', DB::table('club_players')->where('verification_status', 'por_verificar')->exists());
        $check('Jugador libre reclamable (documento coincide con por_verificar)', $this->claimCandidateExists());

        // Torneos: todos los estados.
        foreach (['finished' => 1, 'in_progress' => 2, 'open' => 1, 'draft' => 1] as $status => $min) {
            $check("Torneo en estado '{$status}'", DB::table('tournaments')->where('status', $status)->count() >= $min);
        }
        $check('Torneo privado (no público)', DB::table('tournaments')->where('visibility', 'private')->exists());
        $check('Partidos finalizados', DB::table('tournament_matches')->where('status', 'finished')->exists());
        $check('Partidos programados (a futuro)', DB::table('tournament_matches')->where('status', 'scheduled')->exists());
        $check('Standings calculados', $this->count('standings') > 0);
        $check('Sorteo de desempate (standing_draws)', $this->count('standing_draws') > 0);
        $check('Goles registrados', DB::table('match_events')->where('type', 'goal')->exists());
        $check('Tarjeta roja registrada', DB::table('match_events')->where('type', 'red_card')->exists());
        $check('MVP asignado', DB::table('tournament_matches')->whereNotNull('mvp_team_player_id')->exists());
        $check('Convocatorias con estados variados', DB::table('match_call_ups')->distinct()->count('status') >= 2);
        $check('Movimiento de plantilla (baja)', DB::table('roster_movements')->exists());
        $check('Patrocinadores', $this->count('tournament_sponsors') > 0);
        $check('Invitación de torneo pendiente', DB::table('tournament_invitations')->where('status', 'pending')->exists());
        $check('Recordatorios de partido enviados', $this->count('tournament_match_notifications') > 0);

        // Reputación.
        $check('Acumulado de carrera', DB::table('player_career_stats')->where('matches_played', '>', 0)->exists());
        $check('Fair play calculado', $this->count('fair_play_scores') > 0);
        $check('Ranking de jugadores', DB::table('futgo_rankings')->where('subject_type', 'player')->exists());
        $check('Ranking de equipos', DB::table('futgo_rankings')->where('subject_type', 'team')->exists());
        $check('Logros en >=8 jugadores distintos', DB::table('user_achievements')->distinct()->count('user_id') >= 8);
        $check('Validaciones de credencial (>=5)', $this->count('credential_validations') >= 5);
        $check('Validación no_encontrado', DB::table('credential_validations')->where('result', 'no_encontrado')->exists());
        $check('Validación manual', DB::table('credential_validations')->where('method', 'manual')->exists());

        // Social (solo si el módulo está migrado).
        if (Schema::hasTable('opportunities')) {
            foreach (['BUSCAR_RIVAL', 'BUSCAR_JUGADOR', 'BUSCAR_REFUERZO', 'BUSCAR_EQUIPO'] as $type) {
                $check("Oportunidad tipo {$type}", DB::table('opportunities')->where('type', $type)->exists());
            }
            foreach (['abierta', 'en_negociacion', 'cerrada', 'vencida'] as $st) {
                $check("Oportunidad en estado '{$st}'", DB::table('opportunities')->where('status', $st)->exists());
            }
            foreach (['pendiente', 'aceptada', 'rechazada', 'contrapropuesta'] as $st) {
                $check("Respuesta en estado '{$st}'", DB::table('opportunity_responses')->where('status', $st)->exists());
            }
            $check('Amistoso jugado', DB::table('friendly_matches')->where('status', 'jugado')->exists());
            $check('Amistoso confirmado (pendiente)', DB::table('friendly_matches')->where('status', 'confirmado')->exists());
            $check('Conversaciones con mensajes', $this->count('messages') > 0);
            $check('Mensaje estructurado (del sistema)', DB::table('messages')->where('type', 'structured')->exists());
            $check('Mensaje libre (humano)', DB::table('messages')->where('type', 'free')->exists());
            $check('Seguimientos', $this->count('follows') > 0);
            foreach (['no_show', 'cancelacion_tardia', 'calificacion_positiva', 'calificacion_negativa'] as $rt) {
                $check("Evento de confiabilidad '{$rt}'", DB::table('reliability_events')->where('type', $rt)->exists());
            }
            $check('Scores de confiabilidad', $this->count('reliability_scores') > 0);
            foreach (['oportunidad_publicada', 'resultado_amistoso', 'logro_desbloqueado', 'resultado_torneo'] as $ft) {
                $check("Evento de feed '{$ft}'", DB::table('feed_events')->where('type', $ft)->exists());
            }
            if (Schema::hasTable('venues')) {
                $check('Canchas registradas', $this->count('venues') > 0);
                $check('Cancha vinculada a oportunidad', DB::table('opportunities')->whereNotNull('venue_id')->exists());
            }
        }

        $this->command?->line('');
        if (empty($gaps)) {
            $this->command?->info('✅ Autodiagnóstico: cobertura COMPLETA — no se detectaron vacíos.');
            return;
        }

        $this->command?->warn('⚠️  Autodiagnóstico: se detectaron ' . count($gaps) . ' vacío(s) de cobertura:');
        foreach ($gaps as $g) {
            $this->command?->warn('   • ' . $g);
        }
    }

    private function claimCandidateExists(): bool
    {
        // Un usuario cuyo documento coincide con un club_player 'por_verificar' sin cuenta.
        $docs = DB::table('club_players')
            ->where('verification_status', 'por_verificar')
            ->whereNull('user_id')
            ->whereNotNull('document')
            ->pluck('document');

        return DB::table('users')->whereIn('document', $docs)->exists();
    }

    private function count(string $table): int
    {
        return Schema::hasTable($table) ? DB::table($table)->count() : 0;
    }
}
