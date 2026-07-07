<?php

namespace App\Console\Commands;

use App\Models\Social\ContentReport;
use App\Models\Social\Conversation;
use App\Models\Social\ConversationParticipant;
use App\Models\Social\FeedEvent;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Follow;
use App\Models\Social\Opportunity;
use App\Models\Social\ReliabilityEvent;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\ClubStat;
use App\Models\Torneos\FairPlayScore;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentAdmin;
use App\Models\User;
use App\Services\Social\ReliabilityService;
use App\Services\Torneos\ReputationService;
use Database\Seeders\Demo\DemoData;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Poda el mundo demo (database/seeders/Demo/*) de una base ya en producción,
 * dejando solo los torneos indicados en --keep-tournaments como "ejemplo vivo"
 * y lo que quede naturalmente ligado a ellos. Todo lo demás identificado como
 * demo (torneos/clubes conocidos del seeder + usuarios @futgo.co) se elimina,
 * junto con el contenido social que quede huérfano.
 *
 * Identificación de "demo": los 6 torneos y 26 clubes tienen slug fijo
 * (DemoData::CLUBS + lista de abajo); las cuentas demo siempre usan dominio
 * @futgo.co (ver DemoData). Si alguna cuenta de staff real usa ese dominio,
 * pásala en --keep-emails para no perderla.
 *
 * Uso: corre primero SIN --force (dry-run, solo reporta), revisa el reporte,
 * y luego repite con --force para aplicar.
 */
class PruneDemoData extends Command
{
    /** Los 6 torneos que crea el seeder, por slug (fuente: DemoTorneo{1..6}Seeder). */
    private const DEMO_TOURNAMENT_SLUGS = [
        'liga-medellin-2026',
        'copa-elite-santander-2026',
        'liga-barrial-bogota-2026',
        'torneo-empresarial-cafe-2026',
        'torneo-privado-club-ejecutivos',
        'liga-escolar-sabana-sub13-2026',
    ];

    protected $signature = 'demo:prune
        {--keep-tournaments=liga-barrial-bogota-2026,liga-escolar-sabana-sub13-2026 : Slugs de torneos demo a conservar, separados por coma}
        {--keep-emails=admin@futgo.co : Emails @futgo.co que NUNCA se borran, separados por coma}
        {--force : Ejecuta de verdad; sin esta bandera solo se reporta lo que se borraría}';

    protected $description = 'Elimina el mundo demo del seeder, dejando solo los torneos indicados como ejemplo vivo en producción.';

    public function handle(ReputationService $reputation, ReliabilityService $reliability): int
    {
        $keepSlugs  = $this->splitOption('keep-tournaments');
        $keepEmails = $this->splitOption('keep-emails');

        $unknown = array_diff($keepSlugs, self::DEMO_TOURNAMENT_SLUGS);
        if ($unknown !== []) {
            $this->error('Slug(s) no reconocidos como torneo demo: '.implode(', ', $unknown));
            $this->line('Torneos demo válidos: '.implode(', ', self::DEMO_TOURNAMENT_SLUGS));

            return self::FAILURE;
        }

        $tournamentsToDelete = Tournament::whereIn('slug', self::DEMO_TOURNAMENT_SLUGS)
            ->whereNotIn('slug', $keepSlugs)->get();
        $tournamentsToKeep = Tournament::whereIn('slug', $keepSlugs)->get();

        $demoClubSlugs = collect(DemoData::CLUBS)->pluck('slug')->all();

        // Simulación (solo lectura) de qué clubes/usuarios sobrevivirían, para que
        // el dry-run reporte un número real y no "todo @futgo.co sin distinción".
        $keptTournamentIds  = $tournamentsToKeep->pluck('id');
        $survivingTeamIds   = Team::whereIn('tournament_id', $keptTournamentIds)->pluck('id');
        $survivingClubIds   = Team::whereIn('id', $survivingTeamIds)->pluck('club_id')->filter()->unique();
        $protectedUserIdsPreview = collect()
            ->merge($tournamentsToKeep->pluck('created_by_user_id'))
            ->merge(TournamentAdmin::whereIn('tournament_id', $keptTournamentIds)->pluck('user_id'))
            ->merge(Team::whereIn('id', $survivingTeamIds)->pluck('captain_user_id'))
            ->merge(TeamPlayer::whereIn('team_id', $survivingTeamIds)->pluck('user_id'))
            ->merge(Club::whereIn('id', $survivingClubIds)->pluck('captain_user_id'))
            ->merge(Club::whereIn('id', $survivingClubIds)->pluck('created_by_user_id'))
            ->merge(ClubPlayer::whereIn('club_id', $survivingClubIds)->pluck('user_id'))
            ->filter()->unique();

        $demoClubsToDeleteCount = Club::whereIn('slug', $demoClubSlugs)->whereNotIn('id', $survivingClubIds)->count();
        $demoUsersToDeleteCount = User::where('email', 'like', '%@futgo.co')
            ->whereNotIn('email', $keepEmails)
            ->whereNotIn('id', $protectedUserIdsPreview)
            ->count();

        $this->info('== Torneos demo a CONSERVAR ==');
        foreach ($tournamentsToKeep as $t) {
            $this->line("  - {$t->name} ({$t->slug}) [{$t->status}]");
        }
        if ($tournamentsToKeep->isEmpty()) {
            $this->warn('  (ninguno — revisa --keep-tournaments)');
        }

        $this->info('== Torneos demo a ELIMINAR ==');
        foreach ($tournamentsToDelete as $t) {
            $this->line("  - {$t->name} ({$t->slug}) [{$t->status}] — {$t->teams()->count()} equipos");
        }
        if ($tournamentsToDelete->isEmpty()) {
            $this->line('  (ninguno)');
        }

        $this->info("Clubes demo a ELIMINAR: {$demoClubsToDeleteCount} (sobreviven {$survivingClubIds->count()} ligados a los torneos conservados).");
        $this->info("Usuarios demo (@futgo.co) a ELIMINAR: {$demoUsersToDeleteCount} (se conservan siempre: ".implode(', ', $keepEmails).
            "; además se protege a todo capitán/jugador/creador de lo que sobrevive).");

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN — no se borró nada. Repite el comando con --force para aplicar.');

            return self::SUCCESS;
        }

        $summary = ['tournaments' => 0, 'clubs' => 0, 'users' => 0, 'opportunities' => 0, 'friendly_matches' => 0];

        DB::transaction(function () use ($tournamentsToDelete, $demoClubSlugs, $keepEmails, &$summary) {
            foreach ($tournamentsToDelete as $tournament) {
                $tournament->delete();
                $summary['tournaments']++;
            }

            // Clubes demo que ya no participan en NINGÚN torneo restante.
            $orphanClubs   = Club::whereIn('slug', $demoClubSlugs)->whereDoesntHave('teams')->get();
            $orphanClubIds = $orphanClubs->pluck('id')->all();

            if ($orphanClubIds !== []) {
                $summary['friendly_matches'] += FriendlyMatch::where(fn ($q) => $q
                    ->whereIn('home_club_id', $orphanClubIds)->orWhereIn('away_club_id', $orphanClubIds))
                    ->get()->each->delete()->count();

                $summary['opportunities'] += Opportunity::whereIn('club_id', $orphanClubIds)
                    ->get()->each->delete()->count();
            }

            foreach ($orphanClubs as $club) {
                $club->delete();
                $summary['clubs']++;
            }

            // Usuarios demo (@futgo.co) SIN ningún vínculo que sobreviva. Es crítico
            // calcular esto DESPUÉS de borrar torneos/clubes huérfanos: varias FKs
            // (tournaments.created_by_user_id, teams.captain_user_id,
            // team_players.user_id) son CASCADE, no SET NULL — borrar por error al
            // creador o a un jugador de un torneo que sí se conserva se llevaría por
            // delante ese torneo o sus stats.
            $protectedUserIds = collect()
                ->merge(Tournament::pluck('created_by_user_id'))
                ->merge(TournamentAdmin::pluck('user_id'))
                ->merge(Team::pluck('captain_user_id'))
                ->merge(TeamPlayer::pluck('user_id'))
                ->merge(Club::pluck('captain_user_id'))
                ->merge(Club::pluck('created_by_user_id'))
                ->merge(ClubPlayer::pluck('user_id'))
                ->filter()
                ->unique();

            $demoUsers = User::where('email', 'like', '%@futgo.co')
                ->whereNotIn('email', $keepEmails)
                ->whereNotIn('id', $protectedUserIds)
                ->get();
            foreach ($demoUsers as $user) {
                $user->delete();
                $summary['users']++;
            }

            $this->purgeOrphanSocialResidue();
        });

        $this->info('Reconstruyendo caches de reputación (ranking, fair play, logros, stats de club)...');
        $reputation->rebuildAll();
        $this->info('Reconstruyendo confiabilidad social...');
        $reliability->rebuild();

        $this->newLine();
        $this->info('Listo. Eliminados: '.
            "{$summary['tournaments']} torneos, {$summary['clubs']} clubes, {$summary['users']} usuarios, ".
            "{$summary['opportunities']} oportunidades, {$summary['friendly_matches']} amistosos (huérfanos de esos clubes)."
        );

        return self::SUCCESS;
    }

    /** @return array<int,string> */
    private function splitOption(string $name): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->option($name)))));
    }

    /**
     * Tablas sin FK real hacia sus referencias polimórficas (ver mapa de cascada):
     * futgo_rankings ya se recalcula por delete+insert en RankingService::rebuild().
     * Las demás no se limpian solas al borrar el usuario/club "dueño" — hay que
     * purgar aquí lo que quedó apuntando a nada.
     */
    private function purgeOrphanSocialResidue(): void
    {
        $this->purgeOrphanMorphRows(FeedEvent::class, [['actor_type', 'actor_id'], ['subject_type', 'subject_id']]);
        $this->purgeOrphanMorphRows(Follow::class, [['followable_type', 'followable_id']]);
        $this->purgeOrphanMorphRows(ReliabilityEvent::class, [['subject_type', 'subject_id']]);
        $this->purgeOrphanMorphRows(ReliabilityScore::class, [['subject_type', 'subject_id']]);
        $this->purgeOrphanMorphRows(ContentReport::class, [['reportable_type', 'reportable_id']]);
        $this->purgeOrphanMorphRows(Conversation::class, [['subject_type', 'subject_id']]);

        // FairPlayScore usa 'player'/'team' en vez del morph map estándar 'user'/'club'.
        $survivingUserIds = User::pluck('id');
        $survivingClubIds = Club::pluck('id');
        FairPlayScore::where('subject_type', 'player')->whereNotIn('subject_id', $survivingUserIds)->delete();
        FairPlayScore::where('subject_type', 'team')->whereNotIn('subject_id', $survivingClubIds)->delete();

        ClubStat::whereNotIn('club_id', $survivingClubIds)->delete();

        // Participantes sin usuario NI club (ambos lados eran cuentas/clubes demo ya borrados).
        ConversationParticipant::whereNull('user_id')->whereNull('club_id')->delete();

        // Conversaciones sin ningún participante vivo → no las puede ver nadie, se botan con sus mensajes.
        Conversation::whereDoesntHave('participants')->get()->each->delete();
    }

    /**
     * Borra filas de $modelClass donde alguno de los pares [tipo, id] apunta a
     * una entidad que ya no existe (vía el morph map registrado en AppServiceProvider).
     *
     * @param array<int, array{0:string,1:string}> $morphPairs
     */
    private function purgeOrphanMorphRows(string $modelClass, array $morphPairs): void
    {
        $morphMap = Relation::morphMap();

        /** @var Model $row */
        foreach ($modelClass::query()->get() as $row) {
            foreach ($morphPairs as [$typeCol, $idCol]) {
                $type = $row->{$typeCol};
                $id   = $row->{$idCol};
                if ($type === null || $id === null) {
                    continue;
                }

                $targetClass = $morphMap[$type] ?? (class_exists($type) ? $type : null);
                if ($targetClass === null || ! $targetClass::query()->whereKey($id)->exists()) {
                    $row->delete();
                    break;
                }
            }
        }
    }
}
