<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Services\Torneos\FixtureGeneratorService;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MatchResultController extends Controller
{
    public function __construct(
        private StandingsCalculatorService $standings,
        private PlayerStatsCalculatorService $playerStats,
        private FixtureGeneratorService $fixture,
    ) {}

    public function index(Tournament $tournament): View
    {
        $this->authorizeAccess($tournament);

        $phases = $tournament->phases()
            ->with(['groups', 'matches' => fn($q) => $q
                ->with(['homeTeam', 'awayTeam'])
                ->orderBy('match_number')
            ])
            ->orderBy('order')
            ->get();

        return view('admin.torneos.partidos.index', compact('tournament', 'phases'));
    }

    /** Pantalla de programación: fecha/hora, sede, estado y observaciones del partido. */
    public function editSchedule(Tournament $tournament, TournamentMatch $match): View
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        $match->load(['homeTeam', 'awayTeam', 'phase', 'group']);

        return view('admin.torneos.partidos.programar', compact('tournament', 'match'));
    }

    /** Persiste la programación (no toca marcador ni eventos: eso va por el resultado). */
    public function updateSchedule(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        if ($match->phase->isCompleted()) {
            return back()->with('error', 'La fase está cerrada: no se puede reprogramar.');
        }

        if ($match->isFinished()) {
            return back()->with('error', 'El partido ya está finalizado. Anulá el resultado antes de reprogramarlo.');
        }

        $data = $request->validate([
            'scheduled_at' => ['nullable', 'date'],
            'venue'        => ['nullable', 'string', 'max:100'],
            'status'       => ['required', Rule::in(['scheduled', 'live', 'postponed'])],
            'observations' => ['nullable', 'string', 'max:500'],
        ]);

        $match->scheduled_at = $data['scheduled_at'] ?? null;
        $match->venue        = $data['venue'] ?? null;
        $match->status       = $data['status'];
        $match->observations = $data['observations'] ?? null;
        $match->save();

        return redirect()
            ->route('admin.torneos.partidos.index', $tournament)
            ->with('status', "Programación del partido #{$match->match_number} actualizada.");
    }

    /** Exporta la planilla oficial del partido a PDF (documento maestro imprimible). */
    public function pdf(Tournament $tournament, TournamentMatch $match): Response
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        $match->load([
            'homeTeam.players.user',
            'awayTeam.players.user',
            'phase',
            'group',
        ]);

        $pdf = Pdf::loadView('admin.torneos.partidos.planilla-pdf', [
            'tournament'  => $tournament,
            'match'       => $match,
            'homeRows'    => $this->sheetRows($match->homeTeam),
            'awayRows'    => $this->sheetRows($match->awayTeam),
            'sheet'       => $match->match_sheet ?? [],
            'generatedAt' => now()->locale('es')->isoFormat('D MMM YYYY HH:mm'),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("planilla-partido-{$match->match_number}.pdf");
    }

    /**
     * Plantilla del equipo para la planilla imprimible: lista la nómina completa
     * (titulares y suplentes inscritos) con su identidad. La captura en partido
     * (S. inicial, goles, tarjetas) se llena físicamente sobre el impreso.
     *
     * @return array<int,array<string,mixed>>
     */
    private function sheetRows(?Team $team): array
    {
        if (! $team) {
            return [];
        }

        return $team->players
            ->whereIn('status', ['active', 'inactive'])
            ->sortBy(fn ($p) => $p->jersey_number ?? 999)
            ->map(fn ($p) => [
                'ficha'      => $p->id,
                'name'       => $p->user?->name ?? 'Jugador',
                'number'     => $p->jersey_number,
                'is_captain' => $p->user_id === $team->captain_user_id,
            ])->values()->all();
    }

    public function show(Tournament $tournament, TournamentMatch $match): View
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        $match->load(['homeTeam', 'awayTeam', 'events.teamPlayer.user', 'lineups', 'phase']);

        // Jugadores activos + cualquiera que ya tenga lineup (para re-edición tras red_card)
        $lineupPlayerIds = $match->lineups->pluck('team_player_id');

        $homePlayers = $match->homeTeam
            ? $match->homeTeam->players()
                ->where(fn($q) => $q->where('status', 'active')->orWhereIn('id', $lineupPlayerIds))
                ->with('user')
                ->orderBy('jersey_number')
                ->get()
            : collect();

        $awayPlayers = $match->awayTeam
            ? $match->awayTeam->players()
                ->where(fn($q) => $q->where('status', 'active')->orWhereIn('id', $lineupPlayerIds))
                ->with('user')
                ->orderBy('jersey_number')
                ->get()
            : collect();

        $statsConfig = $tournament->getStatsConfig();

        // Lineups existentes indexadas por team_player_id para pre-llenar el form
        $existingLineups = $match->lineups->keyBy('team_player_id');

        // La planilla solo es editable si el partido está programado o en vivo.
        // Un partido finalizado se consulta/exporta; para corregirlo hay que anularlo.
        $canEdit = $match->isScheduled() || $match->isLive();

        return view('admin.torneos.partidos.resultado', compact(
            'tournament', 'match', 'homePlayers', 'awayPlayers', 'statsConfig', 'existingLineups', 'canEdit'
        ));
    }

    public function store(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        if ($match->phase->isCompleted()) {
            return back()->with('error', 'La fase está cerrada: no se pueden modificar sus resultados.');
        }

        if (! ($match->isScheduled() || $match->isLive())) {
            return back()->with('error', 'Solo se puede ingresar resultado de un partido programado o en vivo.');
        }

        $data = $request->validate([
            'home_score'                    => ['required', 'integer', 'min:0', 'max:99'],
            'away_score'                    => ['required', 'integer', 'min:0', 'max:99'],
            // Planilla oficial: cuerpo arbitral y mesa.
            'referee'                       => ['nullable', 'string', 'max:120'],
            'second_referee'                => ['nullable', 'string', 'max:120'],
            'third_referee'                 => ['nullable', 'string', 'max:120'],
            'timekeeper'                    => ['nullable', 'string', 'max:120'],
            'coordinator'                   => ['nullable', 'string', 'max:120'],
            'referee_notes'                 => ['nullable', 'string', 'max:1000'],
            // Marcador por periodos (informativo; el resultado final manda en standings).
            'home_score_ht'                 => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_score_ht'                 => ['nullable', 'integer', 'min:0', 'max:99'],
            'home_score_et'                 => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_score_et'                 => ['nullable', 'integer', 'min:0', 'max:99'],
            'home_penalties'                => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_penalties'                => ['nullable', 'integer', 'min:0', 'max:99'],
            // Datos por equipo del acta.
            'sheet'                         => ['nullable', 'array'],
            'lineups'                       => ['nullable', 'array'],
            'lineups.*.team_player_id'      => ['required', 'integer', 'exists:team_players,id'],
            'lineups.*.team_id'             => ['required', 'integer', 'exists:teams,id'],
            'lineups.*.started'             => ['nullable', 'boolean'],
            'lineups.*.minute_in'           => ['nullable', 'integer', 'min:0', 'max:120'],
            'lineups.*.minute_out'          => ['nullable', 'integer', 'min:1', 'max:120'],
            'events'                        => ['nullable', 'array'],
            'events.*.team_player_id'       => ['required', 'integer', 'exists:team_players,id'],
            'events.*.type'                 => ['required', 'string', 'in:goal,own_goal,assist,yellow_card,red_card,substitution_in,substitution_out'],
            'events.*.minute'               => ['required', 'integer', 'min:1', 'max:120'],
        ]);

        $homeScore = (int) $data['home_score'];
        $awayScore = (int) $data['away_score'];

        // En empate, los penales (si se cargaron) definen el ganador — necesario
        // para que la eliminatoria pueda avanzar.
        $homePens = isset($data['home_penalties']) ? (int) $data['home_penalties'] : null;
        $awayPens = isset($data['away_penalties']) ? (int) $data['away_penalties'] : null;

        $winnerId = match (true) {
            $homeScore > $awayScore => $match->home_team_id,
            $awayScore > $homeScore => $match->away_team_id,
            $homePens !== null && $awayPens !== null && $homePens > $awayPens => $match->home_team_id,
            $homePens !== null && $awayPens !== null && $awayPens > $homePens => $match->away_team_id,
            default                  => null,
        };

        $sheet = $this->extractSheet($request);

        DB::transaction(function () use ($match, $homeScore, $awayScore, $winnerId, $data, $sheet, $tournament) {
            // 1-3. Actualizar partido + planilla oficial
            $match->home_score     = $homeScore;
            $match->away_score     = $awayScore;
            $match->winner_team_id = $winnerId;
            $match->status         = 'finished';

            // Cuerpo arbitral y mesa.
            $match->referee        = $data['referee'] ?? null;
            $match->second_referee = $data['second_referee'] ?? null;
            $match->third_referee  = $data['third_referee'] ?? null;
            $match->timekeeper     = $data['timekeeper'] ?? null;
            $match->coordinator    = $data['coordinator'] ?? null;
            $match->referee_notes  = $data['referee_notes'] ?? null;

            // Marcador por periodos.
            $match->home_score_ht  = $data['home_score_ht'] ?? null;
            $match->away_score_ht  = $data['away_score_ht'] ?? null;
            $match->home_score_et  = $data['home_score_et'] ?? null;
            $match->away_score_et  = $data['away_score_et'] ?? null;
            $match->home_penalties = $data['home_penalties'] ?? null;
            $match->away_penalties = $data['away_penalties'] ?? null;

            $match->match_sheet    = $sheet;
            $match->save();

            // 4. Limpiar lineups y eventos previos (re-ingreso)
            $match->lineups()->delete();
            $match->events()->delete();

            // 5a. Crear lineups
            foreach ($data['lineups'] ?? [] as $lu) {
                MatchLineup::create([
                    'match_id'       => $match->id,
                    'team_player_id' => $lu['team_player_id'],
                    'team_id'        => $lu['team_id'],
                    'started'        => filter_var($lu['started'] ?? true, FILTER_VALIDATE_BOOLEAN),
                    'minute_in'      => (int) ($lu['minute_in'] ?? 0),
                    'minute_out'     => isset($lu['minute_out']) && $lu['minute_out'] !== '' ? (int) $lu['minute_out'] : null,
                ]);
            }

            // 5b. Crear eventos y aplicar consecuencias automáticas
            foreach ($data['events'] ?? [] as $ev) {
                MatchEvent::create([
                    'match_id'       => $match->id,
                    'team_player_id' => $ev['team_player_id'],
                    'type'           => $ev['type'],
                    'minute'         => $ev['minute'],
                ]);

                // Red card → jugador pasa a inactive automáticamente
                if ($ev['type'] === 'red_card') {
                    TeamPlayer::where('id', $ev['team_player_id'])
                        ->update(['status' => 'inactive']);
                }
            }

            // 6. Recalcular standings
            $phase = $match->phase;
            if ($phase->isGroups()) {
                $this->standings->recalculate($phase);
            }

            // 7. Recalcular player_stats para ambos equipos
            if ($match->home_team_id) {
                $this->playerStats->recalculate($tournament, Team::find($match->home_team_id));
            }
            if ($match->away_team_id) {
                $this->playerStats->recalculate($tournament, Team::find($match->away_team_id));
            }

            // 8. Si todos los partidos de la fase están finished, marcar fase completada
            $this->maybeCompletePhase($phase);
        });

        return redirect()
            ->route('admin.torneos.partidos.index', $tournament)
            ->with('status', "Resultado del partido #{$match->match_number} guardado correctamente.");
    }

    public function markLive(Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        if ($match->phase->isCompleted()) {
            return back()->with('error', 'La fase está cerrada: no se pueden modificar sus partidos.');
        }

        if (! $match->isScheduled()) {
            return back()->with('error', 'Solo se puede marcar en vivo un partido programado.');
        }

        $match->status = 'live';
        $match->save();

        return back()->with('status', "Partido #{$match->match_number} marcado como en vivo.");
    }

    public function destroy(Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        $this->ensureBelongs($tournament, $match);

        if ($match->phase->isCompleted()) {
            return back()->with('error', 'La fase está cerrada: no se pueden anular sus resultados.');
        }

        if (! $match->isFinished()) {
            return back()->with('error', 'Solo se puede anular el resultado de un partido finalizado.');
        }

        DB::transaction(function () use ($match, $tournament) {
            $match->lineups()->delete();
            $match->events()->delete();
            $match->home_score     = null;
            $match->away_score     = null;
            $match->winner_team_id = null;
            $match->status         = 'scheduled';

            // Limpiar datos de resultado del acta (se conservan los árbitros asignados).
            $match->home_score_ht  = null;
            $match->away_score_ht  = null;
            $match->home_score_et  = null;
            $match->away_score_et  = null;
            $match->home_penalties = null;
            $match->away_penalties = null;
            $match->match_sheet    = null;
            $match->save();

            $phase = $match->phase;
            if ($phase->isGroups()) {
                $this->standings->recalculate($phase);
            }
            if ($match->home_team_id) {
                $this->playerStats->recalculate($tournament, Team::find($match->home_team_id));
            }
            if ($match->away_team_id) {
                $this->playerStats->recalculate($tournament, Team::find($match->away_team_id));
            }
        });

        return redirect()
            ->route('admin.torneos.partidos.index', $tournament)
            ->with('status', "Resultado del partido #{$match->match_number} anulado.");
    }

    private function maybeCompletePhase(\App\Models\Torneos\TournamentPhase $phase): void
    {
        $total    = $phase->matches()->whereNotNull('home_team_id')->whereNotNull('away_team_id')->count();
        $finished = $phase->matches()->where('status', 'finished')->count();

        if ($total === 0 || $total !== $finished) {
            return;
        }

        $phase->is_active = false;
        $phase->save();

        // Progresión automática de la eliminatoria: avanza ganadores a la ronda
        // siguiente, puebla el tercer puesto y, si fue la final, cierra el torneo.
        if ($phase->type === 'knockout') {
            $wasFinal = $this->fixture->advanceKnockoutResults($phase);

            if ($wasFinal) {
                $tournament = $phase->tournament;
                if ($tournament->isInProgress()) {
                    $tournament->status = 'finished';
                    $tournament->save();
                }
            }
        }
    }

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

    private function ensureBelongs(Tournament $tournament, TournamentMatch $match): void
    {
        $phaseIds = $tournament->phases()->pluck('id');
        abort_unless($phaseIds->contains($match->phase_id), 404);
    }

    /**
     * Normaliza los datos por equipo del acta (cuerpo técnico, faltas acumulativas,
     * tiempos muertos y firma del capitán) en una estructura JSON segura.
     *
     * @return array<string,array<string,mixed>>
     */
    private function extractSheet(Request $request): array
    {
        $raw   = (array) $request->input('sheet', []);
        $sheet = [];

        foreach (['home', 'away'] as $side) {
            $s = is_array($raw[$side] ?? null) ? $raw[$side] : [];

            $sheet[$side] = [
                'coach'          => $this->cleanString($s['coach'] ?? null),
                'assistant'      => $this->cleanString($s['assistant'] ?? null),
                'delegate'       => $this->cleanString($s['delegate'] ?? null),
                'fouls_1'        => $this->boundedInt($s['fouls_1'] ?? null),
                'fouls_2'        => $this->boundedInt($s['fouls_2'] ?? null),
                'timeouts'       => $this->boundedInt($s['timeouts'] ?? null),
                'captain_signed' => filter_var($s['captain_signed'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $sheet;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 120);
    }

    private function boundedInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, min(99, (int) $value));
    }
}
