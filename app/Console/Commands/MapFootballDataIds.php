<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class MapFootballDataIds extends Command
{
    protected $signature = 'fixtures:map-ids {--dry-run : Mostrar el mapeo sin guardar} {--force : Sobreescribir api_match_id existentes}';

    protected $description = 'Mapea los partidos locales con sus IDs de football-data.org (correr una sola vez).';

    public function handle(FootballDataService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $apiMatches = $service->fetchAllMatches();
        if (empty($apiMatches)) {
            $this->error('No se pudieron obtener partidos de la API. Revisá el token y el log.');

            return self::FAILURE;
        }

        $this->info(sprintf('API devolvió %d partidos.', count($apiMatches)));

        $locals = Game::orderBy('match_datetime')->orderBy('match_number')->get();

        // Resultado del mapeo: [local_id => ['api_id'=>..., 'tipo'=>...]]
        $assignments = [];
        $rows = [];
        $assignedLocalIds = [];

        // Separar partidos API en grupos vs eliminatoria
        $apiGroup = collect($apiMatches)->filter(
            fn ($m) => ! empty($m['homeTeam']['name']) && ! empty($m['awayTeam']['name'])
        );
        $apiKnockout = collect($apiMatches)->reject(
            fn ($m) => ! empty($m['homeTeam']['name']) && ! empty($m['awayTeam']['name'])
        );

        // ---- Estrategia A: fase de grupos (equipos conocidos) ----
        foreach ($apiGroup as $am) {
            $apiDate = $this->apiDate($am['utcDate']);
            $apiHome = $this->normalizeTeamName($am['homeTeam']['name'] ?? '');
            $apiAway = $this->normalizeTeamName($am['awayTeam']['name'] ?? '');

            // Cada enfrentamiento de grupos es único, así que el par de nombres
            // identifica el partido sin depender de la fecha (las fechas locales
            // pueden estar alteradas por el DemoSeeder o diferir por zona horaria).
            $candidates = $locals->filter(function (Game $g) use ($assignedLocalIds) {
                return $g->phase === 'grupos'
                    && ! in_array($g->id, $assignedLocalIds, true);
            });

            $best = null;
            $bestScore = 0.0;
            $bestType = null;

            foreach ($candidates as $g) {
                $localHome = $this->normalizeTeamName($g->home_team);
                $localAway = $this->normalizeTeamName($g->away_team);

                if ($localHome === $apiHome && $localAway === $apiAway) {
                    $best = $g;
                    $bestType = 'exacto';
                    break;
                }

                similar_text($localHome, $apiHome, $pHome);
                similar_text($localAway, $apiAway, $pAway);

                if ($pHome > 80 && $pAway > 80) {
                    $avg = ($pHome + $pAway) / 2;
                    if ($avg > $bestScore) {
                        $bestScore = $avg;
                        $best = $g;
                        $bestType = sprintf('fuzzy %d%%', (int) round($avg));
                    }
                }
            }

            if ($best) {
                $assignedLocalIds[] = $best->id;
                $assignments[$best->id] = ['api_id' => (string) $am['id'], 'tipo' => $bestType];
                $rows[] = [
                    $best->match_number,
                    $best->phase,
                    $best->home_team,
                    $am['homeTeam']['name'] ?? '—',
                    $best->away_team,
                    $am['awayTeam']['name'] ?? '—',
                    $apiDate,
                    $am['id'],
                    $bestType,
                ];
            }
        }

        // ---- Estrategia B: eliminatoria por etapa + posición ----
        // Las fechas locales de eliminatoria son estimadas, pero la cantidad de
        // partidos por ronda coincide exactamente con la API (16/8/4/2/1/1).
        // Emparejamos dentro de cada etapa ordenando por fecha/hora.
        $apiKnockoutByStage = $apiKnockout->groupBy('stage');

        $localKnockoutByStage = $locals
            ->filter(fn (Game $g) => $g->phase !== 'grupos')
            ->groupBy(fn (Game $g) => self::PHASE_TO_STAGE[$g->phase] ?? 'DESCONOCIDO');

        foreach (self::PHASE_TO_STAGE as $phase => $stage) {
            $apiOnStage = ($apiKnockoutByStage->get($stage) ?? collect())
                ->sortBy(fn ($m) => $m['utcDate'])
                ->values();
            /** @var Collection $localOnStage */
            $localOnStage = ($localKnockoutByStage->get($stage) ?? collect())
                ->reject(fn (Game $g) => in_array($g->id, $assignedLocalIds, true))
                ->sortBy(fn (Game $g) => $g->match_datetime->timestamp)
                ->values();

            $pairCount = min($apiOnStage->count(), $localOnStage->count());
            for ($i = 0; $i < $pairCount; $i++) {
                $am = $apiOnStage[$i];
                $g = $localOnStage[$i];

                $assignedLocalIds[] = $g->id;
                $assignments[$g->id] = ['api_id' => (string) $am['id'], 'tipo' => 'eliminatoria-por-etapa'];
                $rows[] = [
                    $g->match_number,
                    $g->phase,
                    $g->home_team,
                    $am['homeTeam']['name'] ?? '(null)',
                    $g->away_team,
                    $am['awayTeam']['name'] ?? '(null)',
                    $this->apiDate($am['utcDate']),
                    $am['id'],
                    'eliminatoria-por-etapa',
                ];
            }
        }

        // Ordenar filas por match_number para lectura
        usort($rows, fn ($a, $b) => $a[0] <=> $b[0]);

        $this->table(
            ['#', 'Fase', 'Local Home', 'API Home', 'Local Away', 'API Away', 'Fecha Bogotá', 'API ID', 'Tipo'],
            $rows,
        );

        // ---- Resumen ----
        $exactos = collect($assignments)->where('tipo', 'exacto')->count();
        $fuzzy = collect($assignments)->filter(fn ($a) => str_starts_with($a['tipo'], 'fuzzy'))->count();
        $elim = collect($assignments)->where('tipo', 'eliminatoria-por-etapa')->count();
        $total = count($assignments);

        $unmatched = $locals->reject(fn (Game $g) => in_array($g->id, $assignedLocalIds, true));

        $this->newLine();
        $this->info("Exactos: {$exactos} / Fuzzy: {$fuzzy} / Eliminatoria por etapa: {$elim}");
        $this->info("Total mapeados: {$total} / 104");

        if ($unmatched->isNotEmpty()) {
            // Detectar pares invertidos: existe en la API el mismo enfrentamiento
            // pero con local/visitante al revés. NO se mapea (el sync asigna goles
            // por lado), pero se avisa para corregir el fixture local.
            $apiGroupByReversedPair = $apiGroup->keyBy(fn ($m) => sprintf(
                '%s|%s',
                $this->normalizeTeamName($m['awayTeam']['name'] ?? ''),
                $this->normalizeTeamName($m['homeTeam']['name'] ?? ''),
            ));

            $this->warn("Sin mapear: {$unmatched->count()} (revisión manual):");
            foreach ($unmatched as $g) {
                $key = $this->normalizeTeamName($g->home_team) . '|' . $this->normalizeTeamName($g->away_team);
                $reversed = $apiGroupByReversedPair->get($key);
                $hint = $reversed
                    ? sprintf(' ⇄ INVERTIDO en API (id %s: %s vs %s) — corregí local/visitante del fixture', $reversed['id'], $reversed['homeTeam']['name'], $reversed['awayTeam']['name'])
                    : '';

                $this->line(sprintf(
                    '  #%d %s — %s vs %s (%s)%s',
                    $g->match_number,
                    $g->phase,
                    $g->home_team,
                    $g->away_team,
                    $g->match_datetime->format('Y-m-d H:i'),
                    $hint,
                ));
            }
        }

        // ---- Persistencia ----
        if ($dryRun) {
            $this->newLine();
            $this->comment('DRY-RUN: no se guardó nada en la BD.');

            return self::SUCCESS;
        }

        $saved = 0;
        $skipped = 0;
        foreach ($assignments as $localId => $data) {
            $game = $locals->firstWhere('id', $localId);
            if (! $game) {
                continue;
            }
            if (! $force && ! is_null($game->api_match_id)) {
                $skipped++;
                continue;
            }
            $game->update(['api_match_id' => $data['api_id']]);
            $saved++;
        }

        $this->newLine();
        $this->info("Guardados: {$saved}. Omitidos (ya tenían id, usá --force para sobreescribir): {$skipped}.");

        return self::SUCCESS;
    }

    private function apiDate(string $utcDate): string
    {
        return Carbon::parse($utcDate)->timezone('America/Bogota')->format('Y-m-d');
    }

    public function normalizeTeamName(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $name = mb_strtolower($name, 'UTF-8');

        $map = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n', 'ç' => 'c', 'à' => 'a', 'è' => 'e',
            'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'â' => 'a', 'ê' => 'e',
            'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ã' => 'a', 'õ' => 'o',
        ];
        $name = strtr($name, $map);
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // El fixture local está en español; la API en inglés.
        // Convertimos el nombre local a su forma canónica (inglés normalizado)
        // para que ambos lados converjan. Los nombres ya en inglés no están
        // en el mapa y pasan sin cambios.
        return self::TEAM_ALIASES[$name] ?? $name;
    }

    /** Fase local => stage de la API (football-data.org). */
    private const PHASE_TO_STAGE = [
        'dieciseisavos' => 'LAST_32',
        'octavos'       => 'LAST_16',
        'cuartos'       => 'QUARTER_FINALS',
        'semifinal'     => 'SEMI_FINALS',
        '3er_puesto'    => 'THIRD_PLACE',
        'final'         => 'FINAL',
    ];

    /** Nombre local normalizado (español) => nombre API normalizado (inglés). */
    private const TEAM_ALIASES = [
        'alemania'             => 'germany',
        'arabia saudita'       => 'saudi arabia',
        'argelia'              => 'algeria',
        'bosnia y herzegovina' => 'bosniaherzegovina',
        'brasil'               => 'brazil',
        'belgica'              => 'belgium',
        'cabo verde'           => 'cape verde islands',
        'corea del sur'        => 'south korea',
        'costa de marfil'      => 'ivory coast',
        'croacia'              => 'croatia',
        'curazao'              => 'curacao',
        'egipto'               => 'egypt',
        'escocia'              => 'scotland',
        'espana'               => 'spain',
        'estados unidos'       => 'united states',
        'francia'              => 'france',
        'inglaterra'           => 'england',
        'irak'                 => 'iraq',
        'japon'                => 'japan',
        'jordania'             => 'jordan',
        'marruecos'            => 'morocco',
        'noruega'              => 'norway',
        'nueva zelanda'        => 'new zealand',
        'paises bajos'         => 'netherlands',
        'rd congo'             => 'congo dr',
        'republica checa'      => 'czechia',
        'sudafrica'            => 'south africa',
        'suecia'               => 'sweden',
        'suiza'                => 'switzerland',
        'turquia'              => 'turkey',
        'tunez'                => 'tunisia',
    ];
}
