<?php

namespace App\Services\Torneos;

use App\Models\Torneos\Standing;
use App\Models\Torneos\TournamentPhase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cierre de una fase de grupos y generación automática de la eliminatoria.
 *
 * Reglas:
 *  - Solo se cierran fases de tipo 'groups'.
 *  - Todos los partidos de la fase (con ambos equipos asignados) deben estar
 *    'finished'. No se permite cierre parcial.
 *  - Debe existir una fase de eliminatoria ('knockout') posterior.
 *
 * Al cerrar:
 *  - La fase queda status='completed', is_active=false (no editable).
 *  - Se avanzan los clasificados a la fase siguiente usando el cruce estándar
 *    ya implementado en FixtureGeneratorService::advanceTeams() (no se reinventa).
 *  - La fase de eliminatoria siguiente queda status='active', is_active=true.
 *
 * El tercer puesto (type='third_place') no se puebla aquí: depende de los
 * perdedores de semifinal y se completa cuando esa ronda termina.
 */
class PhaseClosureService
{
    public function __construct(
        private FixtureGeneratorService $fixture,
    ) {}

    /**
     * Cierra la fase de grupos y genera/activa la eliminatoria siguiente.
     *
     * @throws RuntimeException si la fase no se puede cerrar.
     */
    public function closeGroupPhase(TournamentPhase $phase): TournamentPhase
    {
        $this->assertCanClose($phase);

        return DB::transaction(function () use ($phase) {
            // 1. Marcar la fase como cerrada (no editable).
            $phase->status    = 'completed';
            $phase->is_active = false;
            $phase->save();

            // 2. Avanzar clasificados a la siguiente fase (cruce estándar).
            $this->fixture->advanceTeams($phase);

            // 3. Activar la fase de eliminatoria siguiente.
            $next = $this->nextKnockoutPhase($phase);
            if ($next) {
                $next->status    = 'active';
                $next->is_active = true;
                $next->save();
            }

            return $phase->refresh();
        });
    }

    /**
     * Clasificados proyectados por grupo según standings ya calculados.
     * Para mostrar en la pantalla de cierre y el dashboard.
     *
     * @return Collection<int,array{group:\App\Models\Torneos\TournamentGroup, qualifiers:\Illuminate\Database\Eloquent\Collection}>
     */
    public function projectedQualifiers(TournamentPhase $phase): Collection
    {
        $classifies = (int) $phase->tournament->classifies_per_group;
        $groups = $phase->groups()->orderBy('order')->orderBy('name')->get();

        return $groups->map(function ($group) use ($classifies) {
            $qualifiers = Standing::where('group_id', $group->id)
                ->orderByRaw('position IS NULL')
                ->orderBy('position')
                ->with('team')
                ->limit($classifies)
                ->get();

            return ['group' => $group, 'qualifiers' => $qualifiers];
        });
    }

    /** ¿La fase reúne todas las condiciones para cerrarse? (sin lanzar excepción). */
    public function canClose(TournamentPhase $phase): bool
    {
        return $phase->isGroups()
            && ! $phase->isCompleted()
            && $this->pendingMatches($phase) === 0
            && $this->totalMatches($phase) > 0
            && $this->nextKnockoutPhase($phase) !== null;
    }

    public function totalMatches(TournamentPhase $phase): int
    {
        return $phase->matches()
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->count();
    }

    public function finishedMatches(TournamentPhase $phase): int
    {
        return $phase->matches()
            ->whereNotNull('home_team_id')
            ->whereNotNull('away_team_id')
            ->where('status', 'finished')
            ->count();
    }

    public function pendingMatches(TournamentPhase $phase): int
    {
        return $this->totalMatches($phase) - $this->finishedMatches($phase);
    }

    /** Fase de eliminatoria inmediatamente posterior, si existe. */
    public function nextKnockoutPhase(TournamentPhase $phase): ?TournamentPhase
    {
        return $phase->tournament->phases()
            ->where('type', 'knockout')
            ->where('order', '>', $phase->order)
            ->orderBy('order')
            ->first();
    }

    // ───────────────────────── Validación ─────────────────────────

    private function assertCanClose(TournamentPhase $phase): void
    {
        if (! $phase->isGroups()) {
            throw new RuntimeException('Solo se pueden cerrar fases de grupos.');
        }

        if ($phase->isCompleted()) {
            throw new RuntimeException('Esta fase ya fue cerrada y no se puede volver a cerrar.');
        }

        if ($this->totalMatches($phase) === 0) {
            throw new RuntimeException('La fase no tiene partidos definidos para cerrar.');
        }

        $pending = $this->pendingMatches($phase);
        if ($pending > 0) {
            throw new RuntimeException(
                "No se puede cerrar la fase: quedan {$pending} partido(s) sin finalizar. El cierre debe ser total."
            );
        }

        if (! $this->nextKnockoutPhase($phase)) {
            throw new RuntimeException('No existe una fase de eliminatoria posterior a la cual avanzar los clasificados.');
        }
    }
}
