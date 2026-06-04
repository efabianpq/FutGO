<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Tournament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TournamentController extends Controller
{
    /** Secuencia permitida de estados (solo se avanza, nunca se retrocede). */
    private const STATUS_SEQUENCE = ['draft', 'open', 'in_progress', 'finished'];

    /** Estadísticas que un torneo puede trackear. */
    private const TRACKABLE_STATS = ['goals', 'assists', 'yellow_cards', 'red_cards', 'minutes_played'];

    /** Formatos que organizan equipos en grupos. */
    private const FORMATS_WITH_GROUPS = ['groups_and_knockout', 'round_robin'];

    public function index(): View
    {
        $tournaments = $this->scopedQuery()
            ->withCount('teams')
            ->latest()
            ->get();

        return view('admin.torneos.index', compact('tournaments'));
    }

    public function create(): View
    {
        return view('admin.torneos.create', [
            'tournament'        => new Tournament(['sport' => 'futbol', 'format' => 'groups_and_knockout']),
            'formatsWithGroups' => self::FORMATS_WITH_GROUPS,
            'trackableStats'    => self::TRACKABLE_STATS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateTournament($request);

        $tournament = new Tournament();
        $this->fillFromData($tournament, $data, $request);
        $tournament->status = 'draft';
        $tournament->slug = $this->uniqueSlug($data['name']);
        $tournament->created_by_user_id = $request->user()->id;
        $tournament->save();

        // El creador queda automáticamente como admin del torneo.
        $tournament->admins()->attach($request->user()->id);

        return redirect()
            ->route('admin.torneos.show', $tournament)
            ->with('status', 'Torneo creado correctamente.');
    }

    public function show(Tournament $tournament): View
    {
        $this->authorizeAccess($tournament);

        $matchesQuery = \App\Models\Torneos\TournamentMatch::whereHas(
            'phase',
            fn (Builder $q) => $q->where('tournament_id', $tournament->id)
        );

        $approvedCount = $tournament->teams()->where('status', 'approved')->count();
        $hasFixture    = $tournament->phases()->exists();

        $stats = [
            'teams_count'           => $tournament->teams()->count(),
            'approved_count'        => $approvedCount,
            'matches_scheduled'     => (clone $matchesQuery)->where('status', 'scheduled')->count(),
            'matches_played'        => (clone $matchesQuery)->where('status', 'finished')->count(),
        ];

        return view('admin.torneos.show', [
            'tournament'   => $tournament,
            'stats'        => $stats,
            'nextStatus'   => $this->nextStatus($tournament->status),
            'hasFixture'   => $hasFixture,
            'canGenerate'  => $tournament->isOpen() && ! $hasFixture && $this->hasEnoughApproved($tournament, $approvedCount),
            'phaseSummary' => $this->groupPhaseSummary($tournament, $hasFixture),
        ]);
    }

    public function edit(Tournament $tournament): View
    {
        $this->authorizeAccess($tournament);

        return view('admin.torneos.edit', [
            'tournament'        => $tournament,
            'formatsWithGroups' => self::FORMATS_WITH_GROUPS,
            'trackableStats'    => self::TRACKABLE_STATS,
        ]);
    }

    public function update(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        $data = $this->validateTournament($request);
        $this->fillFromData($tournament, $data, $request);
        $tournament->save();

        return redirect()
            ->route('admin.torneos.show', $tournament)
            ->with('status', 'Cambios guardados.');
    }

    public function updateStatus(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        $data = $request->validate([
            'status' => ['required', Rule::in(self::STATUS_SEQUENCE)],
        ]);

        $currentIndex = array_search($tournament->status, self::STATUS_SEQUENCE, true);
        $targetIndex  = array_search($data['status'], self::STATUS_SEQUENCE, true);

        // Solo se avanza un paso a la vez; nunca se retrocede.
        if ($targetIndex !== $currentIndex + 1) {
            return back()->withErrors([
                'status' => 'Transición de estado no permitida. Solo se puede avanzar al siguiente estado de la secuencia.',
            ]);
        }

        $tournament->status = $data['status'];
        $tournament->save();

        // Al finalizar, consolidar el histórico de todos los jugadores del torneo.
        if ($tournament->status === 'finished') {
            app(\App\Services\Torneos\PlayerCareerStatsService::class)->refreshForTournament($tournament);
        }

        return back()->with('status', 'Estado actualizado a "' . $data['status'] . '".');
    }

    public function destroy(Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        if (! $tournament->isDraft()) {
            return back()->with('error', 'Solo se pueden eliminar torneos en estado borrador.');
        }

        $tournament->delete();

        return redirect()
            ->route('admin.torneos.index')
            ->with('status', 'Torneo eliminado.');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Query base filtrada según el rol: admin global ve todo, torneo_admin solo los suyos. */
    private function scopedQuery(): Builder
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return Tournament::query();
        }

        return Tournament::whereHas(
            'tournamentAdmins',
            fn (Builder $q) => $q->where('user_id', $user->id)
        );
    }

    /** Aborta con 403 si el usuario no administra este torneo (salvo admin global). */
    private function authorizeAccess(Tournament $tournament): void
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return;
        }

        $manages = $tournament->tournamentAdmins()
            ->where('user_id', $user->id)
            ->exists();

        abort_unless($manages, 403);
    }

    private function validateTournament(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'name'                 => ['required', 'string', 'min:3', 'max:100'],
            'sport'                => ['required', 'string', 'max:50'],
            'description'          => ['nullable', 'string'],
            'format'               => ['required', Rule::in(['groups_and_knockout', 'knockout_only', 'round_robin'])],
            'groups_count'         => ['required_if:format,' . implode(',', self::FORMATS_WITH_GROUPS), 'nullable', 'integer', 'min:1', 'max:16'],
            'teams_per_group'      => ['nullable', 'integer', 'min:2', 'max:8', 'required_with:classifies_per_group'],
            'classifies_per_group' => ['nullable', 'integer', 'min:1', 'lt:teams_per_group'],
            'max_teams'            => ['nullable', 'integer', 'min:2', 'max:128'],
            'third_place_match'    => ['nullable', 'boolean'],
            'stats'                => ['nullable', 'array'],
            'stats.*'              => [Rule::in(self::TRACKABLE_STATS)],

            // Visibilidad y categoría
            'visibility' => ['required', Rule::in(['public', 'private'])],
            'category'   => ['required', Rule::in(['libre', 'veteranos', 'sub15', 'sub17', 'sub20', 'femenino', 'mixto'])],
            'city'       => ['nullable', 'string', 'max:80'],
            'venue'      => ['nullable', 'string', 'max:100'],

            // Sistema de puntuación
            'points_win'  => ['required', 'integer', 'min:0', 'max:255', 'gt:points_draw'],
            'points_draw' => ['required', 'integer', 'min:0', 'max:255', 'gte:points_loss'],
            'points_loss' => ['required', 'integer', 'min:0', 'max:255'],

            // Desempates
            'tiebreaker_order'   => ['nullable', 'array'],
            'tiebreaker_order.*' => [Rule::in(\App\Models\Torneos\Tournament::TIEBREAKER_OPTIONS)],
            'knockout_tiebreak'  => ['required', Rule::in(['penalties', 'extra_time_penalties', 'replay'])],

            // Configuración deportiva
            'min_players_per_team' => ['required', 'integer', 'min:1', 'max:255', 'lt:max_players_per_team'],
            'max_players_per_team' => ['required', 'integer', 'min:1', 'max:255'],
            'match_duration'       => ['required', 'integer', 'min:1', 'max:255'],
            'max_substitutions'    => ['required', 'integer', 'min:0', 'max:255'],

            // Inscripción y premios
            'registration_fee'  => ['nullable', 'integer', 'min:0'],
            'prize_description' => ['nullable', 'string', 'max:200'],
            'rules'             => ['nullable', 'string'],
            'logo_url'          => ['nullable', 'string', 'max:255'],
            'banner_url'        => ['nullable', 'string', 'max:255'],

            // Calendario
            'registration_opens_at'  => ['nullable', 'date'],
            'registration_closes_at' => ['nullable', 'date', 'after:registration_opens_at'],
            'starts_at'              => ['nullable', 'date', 'after:registration_closes_at'],
            'ends_at'                => ['nullable', 'date', 'after:starts_at'],
        ], [
            'classifies_per_group.lt' => 'La cantidad que clasifica debe ser menor que los equipos por grupo.',
            'points_win.gt'  => 'Los puntos por victoria deben ser mayores que los puntos por empate.',
            'points_draw.gte' => 'Los puntos por empate deben ser mayores o iguales a los puntos por derrota.',
            'min_players_per_team.lt' => 'El mínimo de jugadores debe ser menor que el máximo.',
            'registration_closes_at.after' => 'El cierre de inscripciones debe ser posterior a la apertura.',
            'starts_at.after' => 'El inicio del torneo debe ser posterior al cierre de inscripciones.',
            'ends_at.after' => 'El fin del torneo debe ser posterior al inicio.',
        ]);

        // max_teams debe coincidir con groups_count * teams_per_group en formatos con grupos.
        $validator->after(function ($validator) use ($request) {
            if (in_array($request->input('format'), self::FORMATS_WITH_GROUPS, true)) {
                $expected = (int) $request->input('groups_count') * (int) $request->input('teams_per_group');
                $given = $request->filled('max_teams') ? (int) $request->input('max_teams') : null;

                if ($given !== null && $given !== $expected) {
                    $validator->errors()->add(
                        'max_teams',
                        "El máximo de equipos debe ser igual a grupos × equipos por grupo ({$expected})."
                    );
                }
            }
        });

        return $validator->validate();
    }

    /** Vuelca los datos validados en el modelo (sin tocar slug/status/creator). */
    private function fillFromData(Tournament $tournament, array $data, Request $request): void
    {
        $tournament->name                 = $data['name'];
        $tournament->sport                = $data['sport'];
        $tournament->description          = $data['description'] ?? null;
        $tournament->format               = $data['format'];
        $tournament->groups_count         = $data['groups_count'] ?? 1;
        $tournament->teams_per_group      = $data['teams_per_group'] ?? 4;
        $tournament->classifies_per_group = $data['classifies_per_group'] ?? 2;
        $tournament->third_place_match    = $request->boolean('third_place_match');
        $tournament->stats_config         = $data['stats'] ?? [];

        // Visibilidad y categoría
        $tournament->visibility = $data['visibility'];
        $tournament->category   = $data['category'];
        $tournament->city       = $data['city'] ?? null;
        $tournament->venue      = $data['venue'] ?? null;

        // Equipos — max_teams se calcula automáticamente en formatos con grupos.
        if (in_array($data['format'], self::FORMATS_WITH_GROUPS, true)) {
            $tournament->max_teams = (int) ($data['groups_count'] ?? 1) * (int) ($data['teams_per_group'] ?? 4);
        } else {
            $tournament->max_teams = $data['max_teams'] ?? 8;
        }

        // Sistema de puntuación
        $tournament->points_win  = $data['points_win'];
        $tournament->points_draw = $data['points_draw'];
        $tournament->points_loss = $data['points_loss'];

        // Desempates
        $tournament->tiebreaker_order  = $data['tiebreaker_order'] ?? $tournament->getDefaultTiebreakerOrder();
        $tournament->knockout_tiebreak = $data['knockout_tiebreak'];

        // Configuración deportiva
        $tournament->min_players_per_team = $data['min_players_per_team'];
        $tournament->max_players_per_team = $data['max_players_per_team'];
        $tournament->match_duration       = $data['match_duration'];
        $tournament->max_substitutions    = $data['max_substitutions'];

        // Inscripción y premios
        $tournament->registration_fee  = $data['registration_fee'] ?? 0;
        $tournament->prize_description  = $data['prize_description'] ?? null;
        $tournament->rules              = $data['rules'] ?? null;
        $tournament->logo_url           = $data['logo_url'] ?? null;
        $tournament->banner_url         = $data['banner_url'] ?? null;

        // Calendario
        $tournament->registration_opens_at  = $data['registration_opens_at'] ?? null;
        $tournament->registration_closes_at = $data['registration_closes_at'] ?? null;
        $tournament->starts_at               = $data['starts_at'] ?? null;
        $tournament->ends_at                 = $data['ends_at'] ?? null;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Tournament::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    /** ¿Hay equipos aprobados suficientes para generar el fixture de este formato? */
    private function hasEnoughApproved(Tournament $tournament, int $approved): bool
    {
        return match ($tournament->format) {
            'groups_and_knockout',
            'round_robin'   => $approved === (int) $tournament->max_teams,
            'knockout_only' => $approved >= 4 && ($approved & ($approved - 1)) === 0,
            default         => false,
        };
    }

    /**
     * Resumen de la fase de grupos abierta (no cerrada) para la tarjeta del dashboard:
     * estado, partidos, clasificados proyectados y si se puede cerrar.
     *
     * @return array<string,mixed>|null
     */
    private function groupPhaseSummary(Tournament $tournament, bool $hasFixture): ?array
    {
        if (! $hasFixture) {
            return null;
        }

        $phase = $tournament->phases()
            ->where('type', 'groups')
            ->where('status', '!=', 'completed')
            ->orderBy('order')
            ->first();

        if (! $phase) {
            return null;
        }

        $closure = app(\App\Services\Torneos\PhaseClosureService::class);

        $total    = $closure->totalMatches($phase);
        $finished = $closure->finishedMatches($phase);
        $next     = $closure->nextKnockoutPhase($phase);

        return [
            'phase'          => $phase,
            'total'          => $total,
            'finished'       => $finished,
            'pending'        => $total - $finished,
            'qualifiers'     => (int) $tournament->groups_count * (int) $tournament->classifies_per_group,
            'next_phase'     => $next,
            'can_close'      => $closure->canClose($phase),
        ];
    }

    private function nextStatus(string $current): ?string
    {
        $index = array_search($current, self::STATUS_SEQUENCE, true);

        if ($index === false || $index + 1 >= count(self::STATUS_SEQUENCE)) {
            return null;
        }

        return self::STATUS_SEQUENCE[$index + 1];
    }
}
