<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Standing;
use App\Models\Torneos\TournamentGroup;
use App\Models\Torneos\TournamentMatch;
use App\Models\Torneos\TournamentPhase;
use App\Models\Torneos\Tournament;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Genera el fixture de un torneo según su formato.
 *
 *  - groups_and_knockout: fase de grupos round-robin + rondas de eliminatoria (placeholders)
 *  - round_robin: una única fase con un solo grupo, todos contra todos
 *  - knockout_only: rondas de eliminatoria; la primera con equipos aleatorios, el resto placeholders
 *
 * Los match_number son secuenciales y únicos a nivel TORNEO (no por fase).
 * Al generar exitosamente, el torneo pasa de 'open' a 'in_progress'.
 */
class FixtureGeneratorService
{
    /** Contador global de match_number para el torneo en curso. */
    private int $nextMatchNumber = 1;

    /**
     * Genera todo el fixture dentro de una transacción y devuelve un resumen.
     *
     * @return array{phases:int, groups:int, matches:int}
     * @throws RuntimeException si el torneo no cumple los requisitos del formato.
     */
    public function generate(Tournament $tournament): array
    {
        $this->assertCanGenerate($tournament);

        $approved = $tournament->teams()
            ->where('status', 'approved')
            ->orderBy('id')
            ->get();

        $this->assertEnoughTeams($tournament, $approved);

        $this->nextMatchNumber = 1;

        return DB::transaction(function () use ($tournament, $approved) {
            match ($tournament->format) {
                'groups_and_knockout' => $this->generateGroupsAndKnockout($tournament, $approved),
                'round_robin'         => $this->generateRoundRobin($tournament, $approved),
                'knockout_only'       => $this->generateKnockoutOnly($tournament, $approved),
                default               => throw new RuntimeException("Formato desconocido: {$tournament->format}."),
            };

            // El torneo arranca: open → in_progress.
            $tournament->status = 'in_progress';
            $tournament->save();

            return $this->summary($tournament);
        });
    }

    /**
     * Toma los clasificados de la fase completada y los asigna a los placeholders
     * de la fase de eliminatoria siguiente, respetando el cruce de llaves.
     *
     *  - Fase de grupos completada: clasifican los primeros classifies_per_group de
     *    cada grupo según standings; se cruzan (1ro de un grupo vs 2do de otro).
     *  - Fase de eliminatoria completada: avanzan los ganadores de cada partido.
     *
     * @throws RuntimeException si no hay fase siguiente o faltan datos de clasificación.
     */
    public function advanceTeams(TournamentPhase $completedPhase): void
    {
        $tournament = $completedPhase->tournament;

        $nextPhase = $tournament->phases()
            ->where('type', 'knockout')
            ->where('order', '>', $completedPhase->order)
            ->orderBy('order')
            ->first();

        if (! $nextPhase) {
            throw new RuntimeException('No hay una fase de eliminatoria posterior a la cual avanzar.');
        }

        if ($completedPhase->type === 'groups') {
            $flat = $this->crossBracketOrder(
                $this->qualifiersByGroup($completedPhase, (int) $tournament->classifies_per_group),
                (int) $tournament->classifies_per_group,
            );
        } else {
            $flat = $this->winnersOf($completedPhase);
        }

        $this->assignBracket($nextPhase, $flat);
    }

    /**
     * Progresión automática de la eliminatoria al finalizar una ronda de knockout.
     * Es defensiva: NO lanza excepciones (corre dentro de la transacción del
     * resultado, no debe revertir el guardado). Solo avanza si todos los partidos
     * de la ronda tienen ganador definido.
     *
     *  - Puebla el partido de Tercer Puesto con los perdedores de la semifinal.
     *  - Asigna los ganadores a la ronda siguiente y la activa.
     *
     * @return bool true si la ronda fue la final (no hay ronda siguiente).
     */
    public function advanceKnockoutResults(TournamentPhase $completedPhase): bool
    {
        if ($completedPhase->type !== 'knockout') {
            return false;
        }

        $tournament = $completedPhase->tournament;

        $realMatches = $completedPhase->matches()
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->orderBy('match_number')
            ->get();

        // Si algún partido no tiene ganador (empate sin definición), no se avanza.
        if ($realMatches->isEmpty() || $realMatches->contains(fn ($m) => ! $m->winner_team_id)) {
            return false;
        }

        // Tercer puesto: perdedores de la semifinal (ronda de exactamente 2 partidos).
        if ($realMatches->count() === 2) {
            $third = $tournament->phases()->where('type', 'third_place')->first();
            if ($third && $third->matches()->whereNotNull('home_team_id')->doesntExist()) {
                $losers = $realMatches->map(fn ($m) => $m->winner_team_id === $m->home_team_id ? $m->away_team_id : $m->home_team_id)->all();
                $this->assignBracket($third, $losers);
                $third->status = 'active';
                $third->is_active = true;
                $third->save();
            }
        }

        // Ganadores → ronda de eliminatoria siguiente.
        $next = $tournament->phases()
            ->where('type', 'knockout')
            ->where('order', '>', $completedPhase->order)
            ->orderBy('order')
            ->first();

        if (! $next) {
            return true; // Era la final: no hay ronda posterior.
        }

        $this->assignBracket($next, $realMatches->pluck('winner_team_id')->all());
        $next->status = 'active';
        $next->is_active = true;
        $next->save();

        return false;
    }

    // ───────────────────────── Formato LIGA (H6) ─────────────────────────

    /**
     * H6: activa un torneo en formato "liga". Crea la fase única (tipo groups,
     * un solo grupo con todos los aprobados) SIN partidos, y pasa el torneo a
     * in_progress. El admin agrega los partidos luego (manual o auto round-robin).
     *
     * @throws RuntimeException si el torneo no cumple los requisitos.
     */
    public function setupLeague(Tournament $tournament): TournamentPhase
    {
        if ($tournament->format !== 'liga') {
            throw new RuntimeException('Solo los torneos en formato liga se activan de esta forma.');
        }
        if (! $tournament->isOpen()) {
            throw new RuntimeException("El torneo debe estar en estado 'open' para activarlo.");
        }
        if ($tournament->phases()->exists()) {
            throw new RuntimeException('El torneo ya fue activado.');
        }

        $approved = $tournament->teams()->where('status', 'approved')->orderBy('id')->get();
        if ($approved->count() < 2) {
            throw new RuntimeException('Se requieren al menos 2 equipos aprobados para activar la liga.');
        }

        return DB::transaction(function () use ($tournament, $approved) {
            $phase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name'          => 'Liga',
                'type'          => 'groups',
                'order'         => 1,
                'is_active'     => true,
                'status'        => 'active',
            ]);

            $group = TournamentGroup::create([
                'phase_id' => $phase->id,
                'name'     => 'Tabla general',
                'order'    => 1,
            ]);

            foreach ($approved as $team) {
                $group->teams()->attach($team->id);
            }

            $tournament->status = 'in_progress';
            $tournament->save();

            return $phase;
        });
    }

    /**
     * H6: agrega un partido individual a la fase de liga (lo crea el admin a mano).
     *
     * @throws RuntimeException si el torneo/fase no admiten agregar partidos.
     */
    public function addLeagueMatch(Tournament $tournament, int $homeTeamId, int $awayTeamId, ?string $scheduledAt = null, ?string $venue = null): TournamentMatch
    {
        $phase = $this->leaguePhase($tournament);

        if ($homeTeamId === $awayTeamId) {
            throw new RuntimeException('Un equipo no puede jugar contra sí mismo.');
        }

        // Ambos equipos deben pertenecer al grupo de la liga (aprobados).
        $group = $phase->groups()->first();
        $teamIds = $group->teams()->pluck('teams.id')->all();
        if (! in_array($homeTeamId, $teamIds, true) || ! in_array($awayTeamId, $teamIds, true)) {
            throw new RuntimeException('Ambos equipos deben estar inscritos y aprobados en la liga.');
        }

        return TournamentMatch::create([
            'phase_id'     => $phase->id,
            'group_id'     => $group->id,
            'home_team_id' => $homeTeamId,
            'away_team_id' => $awayTeamId,
            'status'       => 'scheduled',
            'scheduled_at' => $scheduledAt,
            'venue'        => $venue,
            'match_number' => $this->nextTournamentMatchNumber($tournament),
        ]);
    }

    /**
     * H6: auto-genera todos los partidos round-robin (todos contra todos) que
     * falten en la fase de liga. No duplica enfrentamientos ya creados.
     *
     * @return int cantidad de partidos creados.
     */
    public function generateLeagueRoundRobin(Tournament $tournament): int
    {
        $phase = $this->leaguePhase($tournament);
        $group = $phase->groups()->first();
        $teamIds = $group->teams()->orderBy('teams.id')->pluck('teams.id')->all();

        // Enfrentamientos ya existentes (en cualquier orden) para no duplicar.
        $existing = $phase->matches()->get(['home_team_id', 'away_team_id'])
            ->map(fn ($m) => $this->pairKey($m->home_team_id, $m->away_team_id))
            ->filter()
            ->flip();

        $created = 0;
        return DB::transaction(function () use ($teamIds, $existing, $phase, $group, $tournament, &$created) {
            $n = count($teamIds);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $key = $this->pairKey($teamIds[$i], $teamIds[$j]);
                    if ($existing->has($key)) {
                        continue;
                    }
                    TournamentMatch::create([
                        'phase_id'     => $phase->id,
                        'group_id'     => $group->id,
                        'home_team_id' => $teamIds[$i],
                        'away_team_id' => $teamIds[$j],
                        'status'       => 'scheduled',
                        'match_number' => $this->nextTournamentMatchNumber($tournament),
                    ]);
                    $created++;
                }
            }
            return $created;
        });
    }

    /**
     * H6: genera la eliminatoria de una liga tomando los mejores N de la tabla
     * (classifies_per_group). N debe ser potencia de 2 (>= 2). Cierra la fase
     * de liga, crea las rondas de eliminatoria, asigna el bracket por seeding
     * (1ro vs último) y activa la primera ronda.
     *
     * @throws RuntimeException si no se cumplen las condiciones.
     */
    public function generateKnockoutFromStandings(Tournament $tournament): array
    {
        if ($tournament->format !== 'liga') {
            throw new RuntimeException('Solo aplica a torneos en formato liga.');
        }

        $phase = $this->leaguePhase($tournament);
        $group = $phase->groups()->first();

        // No debe existir ya una eliminatoria.
        if ($tournament->phases()->where('type', 'knockout')->exists()) {
            throw new RuntimeException('La eliminatoria ya fue generada.');
        }

        // Todos los partidos de la liga deben estar finalizados.
        $pending = $phase->matches()->where('status', '!=', 'finished')->count();
        if ($phase->matches()->count() === 0) {
            throw new RuntimeException('La liga no tiene partidos cargados.');
        }
        if ($pending > 0) {
            throw new RuntimeException("Quedan {$pending} partidos sin finalizar en la liga.");
        }

        $classifies = (int) $tournament->classifies_per_group;
        if ($classifies < 2 || ! $this->isPowerOfTwo($classifies)) {
            throw new RuntimeException('La cantidad de clasificados a eliminatoria debe ser potencia de 2 (2, 4, 8...).');
        }

        // Mejores N de la tabla.
        $standings = Standing::where('group_id', $group->id)
            ->orderByRaw('position IS NULL')
            ->orderBy('position')
            ->limit($classifies)
            ->get();

        if ($standings->count() < $classifies) {
            throw new RuntimeException('No hay suficientes equipos en la tabla para clasificar.');
        }

        $seeds = $standings->pluck('team_id')->all();

        return DB::transaction(function () use ($tournament, $phase, $classifies, $seeds) {
            // Cierra la fase de liga.
            $phase->status = 'completed';
            $phase->is_active = false;
            $phase->save();

            // El contador global parte del máximo actual.
            $this->nextMatchNumber = $this->nextTournamentMatchNumber($tournament);

            // Crea las rondas de eliminatoria a partir del order siguiente.
            $startOrder = (int) $tournament->phases()->max('order') + 1;
            $phases = $this->buildKnockoutPhases($tournament, $startOrder, $classifies);

            // Asigna el bracket por seeding (1ro vs último) y activa la primera ronda.
            $firstRound = $phases[0];
            $firstRound->is_active = true;
            $firstRound->status = 'active';
            $firstRound->save();

            $this->assignBracket($firstRound, $this->seedFlatOrder($seeds));

            return $this->summary($tournament);
        });
    }

    /** Fase de liga (tipo groups) del torneo. */
    private function leaguePhase(Tournament $tournament): TournamentPhase
    {
        $phase = $tournament->phases()->where('type', 'groups')->orderBy('order')->first();
        if (! $phase) {
            throw new RuntimeException('La liga aún no fue activada.');
        }
        return $phase;
    }

    /** Siguiente match_number a nivel torneo (máximo actual + 1). */
    private function nextTournamentMatchNumber(Tournament $tournament): int
    {
        $phaseIds = $tournament->phases()->pluck('id');
        $max = (int) TournamentMatch::whereIn('phase_id', $phaseIds)->max('match_number');
        return $max + 1;
    }

    /** Clave normalizada de un enfrentamiento (sin importar local/visitante). */
    private function pairKey(?int $a, ?int $b): ?string
    {
        if (! $a || ! $b) {
            return null;
        }
        return $a < $b ? "{$a}-{$b}" : "{$b}-{$a}";
    }

    // ───────────────────────── Generadores por formato ─────────────────────────

    private function generateGroupsAndKnockout(Tournament $tournament, Collection $approved): void
    {
        $groupsPhase = TournamentPhase::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Fase de Grupos',
            'type'          => 'groups',
            'order'         => 1,
            'is_active'     => true,
        ]);

        $this->createGroupsAndAssign($groupsPhase, $approved, (int) $tournament->groups_count);
        $this->generateGroupMatches($groupsPhase);

        $advancing = (int) $tournament->groups_count * (int) $tournament->classifies_per_group;
        $this->buildKnockoutPhases($tournament, 2, $advancing);
    }

    private function generateRoundRobin(Tournament $tournament, Collection $approved): void
    {
        $phase = TournamentPhase::create([
            'tournament_id' => $tournament->id,
            'name'          => 'Todos contra todos',
            'type'          => 'groups',
            'order'         => 1,
            'is_active'     => true,
        ]);

        // Un único grupo con todos los equipos: todos contra todos.
        $group = TournamentGroup::create([
            'phase_id' => $phase->id,
            'name'     => 'Único',
            'order'    => 1,
        ]);

        foreach ($approved as $team) {
            $group->teams()->attach($team->id);
        }

        $this->generateGroupMatches($phase);
    }

    private function generateKnockoutOnly(Tournament $tournament, Collection $approved): void
    {
        $teamIds = $approved->pluck('id')->all();
        shuffle($teamIds); // emparejamientos iniciales aleatorios

        $advancing = count($teamIds);
        $phases = $this->buildKnockoutPhases($tournament, 1, $advancing);

        // La primera ronda se completa con los equipos; el resto queda placeholder.
        $firstRound = $phases[0];
        $firstRound->is_active = true;
        $firstRound->save();

        $this->assignBracket($firstRound, $teamIds);
    }

    // ───────────────────────── Construcción ─────────────────────────

    /**
     * Crea los grupos (A, B, C...) y reparte los equipos de forma equilibrada
     * (equipo k → grupo k % groups_count).
     */
    private function createGroupsAndAssign(TournamentPhase $phase, Collection $approved, int $groupsCount): void
    {
        $groups = [];
        for ($i = 0; $i < $groupsCount; $i++) {
            $groups[$i] = TournamentGroup::create([
                'phase_id' => $phase->id,
                'name'     => $this->groupLetter($i),
                'order'    => $i + 1,
            ]);
        }

        foreach ($approved->values() as $index => $team) {
            $groups[$index % $groupsCount]->teams()->attach($team->id);
        }
    }

    /**
     * Genera los partidos round-robin de cada grupo de la fase: N*(N-1)/2 por grupo.
     * Usa el contador global de match_number.
     */
    private function generateGroupMatches(TournamentPhase $phase): void
    {
        $groups = $phase->groups()->orderBy('order')->orderBy('name')->get();

        foreach ($groups as $group) {
            $teamIds = $group->teams()->orderBy('teams.id')->pluck('teams.id')->all();
            $n = count($teamIds);

            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    TournamentMatch::create([
                        'phase_id'     => $phase->id,
                        'group_id'     => $group->id,
                        'home_team_id' => $teamIds[$i],
                        'away_team_id' => $teamIds[$j],
                        'status'       => 'scheduled',
                        'match_number' => $this->nextMatchNumber++,
                    ]);
                }
            }
        }
    }

    /**
     * Crea las fases de eliminatoria como placeholders (partidos sin equipos),
     * halviazando la cantidad de equipos por ronda hasta la final.
     * Si hay tercer puesto y existió una semifinal, agrega esa fase.
     *
     * @return array<int,TournamentPhase> fases creadas, en orden.
     */
    protected function buildKnockoutPhases(Tournament $tournament, int $startOrder, int $advancing): array
    {
        $phases = [];
        $teamsInRound = $advancing;
        $order = $startOrder;
        $hadSemifinal = false;

        while ($teamsInRound >= 2) {
            $matchesThisRound = intdiv($teamsInRound, 2);

            if ($matchesThisRound === 2) {
                $hadSemifinal = true;
            }

            $phase = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name'          => $this->roundName($matchesThisRound),
                'type'          => 'knockout',
                'order'         => $order,
                'is_active'     => false,
            ]);

            for ($m = 0; $m < $matchesThisRound; $m++) {
                TournamentMatch::create([
                    'phase_id'     => $phase->id,
                    'home_team_id' => null,
                    'away_team_id' => null,
                    'status'       => 'scheduled',
                    'match_number' => $this->nextMatchNumber++,
                ]);
            }

            $phases[] = $phase;
            $teamsInRound = $matchesThisRound + ($teamsInRound % 2); // ganadores (+ bye si impar)
            $order++;
        }

        if ($tournament->third_place_match && $hadSemifinal) {
            $thirdPlace = TournamentPhase::create([
                'tournament_id' => $tournament->id,
                'name'          => 'Tercer Puesto',
                'type'          => 'third_place',
                'order'         => $order,
                'is_active'     => false,
            ]);
            TournamentMatch::create([
                'phase_id'     => $thirdPlace->id,
                'home_team_id' => null,
                'away_team_id' => null,
                'status'       => 'scheduled',
                'match_number' => $this->nextMatchNumber++,
            ]);
            $phases[] = $thirdPlace;
        }

        return $phases;
    }

    /**
     * Asigna equipos a los partidos (placeholders) de una fase de eliminatoria,
     * emparejando de a pares consecutivos: teams[0] vs teams[1], teams[2] vs teams[3]...
     * No modifica match_number (ya asignado al crear los placeholders).
     *
     * @param array<int,int|null> $teams
     */
    private function assignBracket(TournamentPhase $phase, array $teams): void
    {
        $pairs = array_chunk($teams, 2);
        $existing = $phase->matches()->orderBy('match_number')->get()->values();

        foreach ($pairs as $i => $pair) {
            $home = $pair[0] ?? null;
            $away = $pair[1] ?? null;

            if (isset($existing[$i])) {
                $match = $existing[$i];
                $match->home_team_id = $home;
                $match->away_team_id = $away;
                $match->save();
            } else {
                TournamentMatch::create([
                    'phase_id'     => $phase->id,
                    'home_team_id' => $home,
                    'away_team_id' => $away,
                    'status'       => 'scheduled',
                    'match_number' => $this->nextMatchNumber++,
                ]);
            }
        }
    }

    // ───────────────────────── Cruce / clasificación ─────────────────────────

    /**
     * Clasificados de cada grupo según standings, ordenados por posición.
     *
     * @return array<int,array<int,int>> [indiceGrupo => [teamIdPos1, teamIdPos2, ...]]
     */
    private function qualifiersByGroup(TournamentPhase $groupsPhase, int $classifies): array
    {
        $groups = $groupsPhase->groups()->orderBy('order')->orderBy('name')->get();
        $byGroup = [];

        foreach ($groups->values() as $gIndex => $group) {
            $standings = Standing::where('group_id', $group->id)
                ->orderByRaw('position IS NULL')
                ->orderBy('position')
                ->limit($classifies)
                ->get();

            if ($standings->count() < $classifies) {
                throw new RuntimeException(
                    "El grupo {$group->name} no tiene suficientes posiciones calculadas en standings para clasificar."
                );
            }

            $byGroup[$gIndex] = $standings->pluck('team_id')->all();
        }

        return $byGroup;
    }

    /**
     * Construye el orden plano del bracket cruzando 1ros y 2dos de grupos distintos.
     *
     * Caso estándar (classifies == 2, grupos pares): cruza grupos consecutivos
     *   A/B → A1 vs B2 , B1 vs A2     C/D → C1 vs D2 , D1 vs C2
     * Caso general: seeding plano (1ro vs último).
     *
     * @param array<int,array<int,int>> $byGroup
     * @return array<int,int>
     */
    private function crossBracketOrder(array $byGroup, int $classifies): array
    {
        $groupCount = count($byGroup);

        if ($classifies === 2 && $groupCount % 2 === 0) {
            $flat = [];
            for ($i = 0; $i < $groupCount; $i += 2) {
                $A = $byGroup[$i];
                $B = $byGroup[$i + 1];
                $flat[] = $A[0];  // A1
                $flat[] = $B[1];  // B2
                $flat[] = $B[0];  // B1
                $flat[] = $A[1];  // A2
            }
            return $flat;
        }

        // Fallback: seeding plano (todos los 1ros, luego 2dos, ...) y cruce 1ro vs último.
        $seeds = [];
        for ($pos = 0; $pos < $classifies; $pos++) {
            foreach ($byGroup as $teams) {
                if (isset($teams[$pos])) {
                    $seeds[] = $teams[$pos];
                }
            }
        }

        return $this->seedFlatOrder($seeds);
    }

    /**
     * Empareja una lista seedeada cruzando mejor vs peor: s[0] vs s[n-1], s[1] vs s[n-2]...
     *
     * @param array<int,int> $seeds
     * @return array<int,int>
     */
    private function seedFlatOrder(array $seeds): array
    {
        $flat = [];
        $left = 0;
        $right = count($seeds) - 1;

        while ($left < $right) {
            $flat[] = $seeds[$left];
            $flat[] = $seeds[$right];
            $left++;
            $right--;
        }

        return $flat;
    }

    /**
     * Ganadores de una fase de eliminatoria, ordenados por match_number.
     *
     * @return array<int,int>
     */
    private function winnersOf(TournamentPhase $phase): array
    {
        $matches = $phase->matches()->orderBy('match_number')->get();
        $winners = [];

        foreach ($matches as $match) {
            if (! $match->winner_team_id) {
                throw new RuntimeException(
                    "El partido #{$match->match_number} de la fase \"{$phase->name}\" no tiene ganador definido."
                );
            }
            $winners[] = $match->winner_team_id;
        }

        return $winners;
    }

    /**
     * Partidos de rondas posteriores que ya heredaron un equipo desde $match
     * (mismo mapeo posicional que assignBracket, en sentido inverso): el
     * ganador avanza a la ronda de eliminatoria siguiente y, si $match es de
     * semifinal (fase con exactamente 2 partidos), el perdedor alimenta el
     * partido de Tercer Puesto. No hay next_match_id persistido: se deriva
     * del orden por match_number dentro de cada fase.
     *
     * @return array<int,TournamentMatch>
     */
    public function downstreamMatchesFor(TournamentMatch $match): array
    {
        $phase = $match->phase;
        if (! $phase || $phase->type !== 'knockout') {
            return [];
        }

        $ordered = $phase->matches()->orderBy('match_number')->get()->values();
        $position = $ordered->search(fn ($m) => $m->id === $match->id);
        if ($position === false) {
            return [];
        }
        $pairIndex = intdiv($position, 2);

        $tournament = $phase->tournament;
        $downstream = [];

        $next = $tournament->phases()
            ->where('type', 'knockout')
            ->where('order', '>', $phase->order)
            ->orderBy('order')
            ->first();

        if ($next) {
            $nextMatch = $next->matches()->orderBy('match_number')->get()->values()->get($pairIndex);
            if ($nextMatch) {
                $downstream[] = $nextMatch;
            }
        }

        if ($ordered->count() === 2) {
            $third = $tournament->phases()->where('type', 'third_place')->first();
            $thirdMatch = $third?->matches()->first();
            if ($thirdMatch) {
                $downstream[] = $thirdMatch;
            }
        }

        return $downstream;
    }

    // ───────────────────────── Validaciones ─────────────────────────

    private function assertCanGenerate(Tournament $tournament): void
    {
        if (! $tournament->isOpen()) {
            throw new RuntimeException("El torneo debe estar en estado 'open' para generar el fixture.");
        }

        if ($tournament->phases()->exists()) {
            throw new RuntimeException('El torneo ya tiene un fixture generado.');
        }
    }

    private function assertEnoughTeams(Tournament $tournament, Collection $approved): void
    {
        $count = $approved->count();

        switch ($tournament->format) {
            case 'groups_and_knockout':
            case 'round_robin':
                $required = (int) $tournament->max_teams;
                if ($count !== $required) {
                    throw new RuntimeException(
                        "Se requieren exactamente {$required} equipos aprobados para generar el fixture; hay {$count}."
                    );
                }
                break;

            case 'knockout_only':
                if ($count < 4) {
                    throw new RuntimeException("La eliminatoria requiere al menos 4 equipos aprobados; hay {$count}.");
                }
                if (! $this->isPowerOfTwo($count)) {
                    throw new RuntimeException("La eliminatoria requiere una cantidad de equipos potencia de 2 (4, 8, 16...); hay {$count}.");
                }
                break;

            default:
                throw new RuntimeException("Formato desconocido: {$tournament->format}.");
        }
    }

    // ───────────────────────── Utilidades ─────────────────────────

    private function summary(Tournament $tournament): array
    {
        $phaseIds = $tournament->phases()->pluck('id');

        return [
            'phases'  => $phaseIds->count(),
            'groups'  => TournamentGroup::whereIn('phase_id', $phaseIds)->count(),
            'matches' => TournamentMatch::whereIn('phase_id', $phaseIds)->count(),
        ];
    }

    private function isPowerOfTwo(int $n): bool
    {
        return $n >= 1 && ($n & ($n - 1)) === 0;
    }

    private function groupLetter(int $index): string
    {
        $letter = '';
        $index++;
        while ($index > 0) {
            $index--;
            $letter = chr(65 + ($index % 26)) . $letter;
            $index = intdiv($index, 26);
        }
        return $letter;
    }

    private function roundName(int $matchesThisRound): string
    {
        return match ($matchesThisRound) {
            1       => 'Final',
            2       => 'Semifinal',
            4       => 'Cuartos de Final',
            8       => 'Octavos de Final',
            16      => 'Dieciseisavos de Final',
            default => 'Ronda de ' . ($matchesThisRound * 2) . ' equipos',
        };
    }
}
