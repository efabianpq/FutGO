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
    /** Marcador convencional para un partido ganado por W.O. (no presentación). */
    private const WALKOVER_WIN_SCORE  = 3;
    private const WALKOVER_LOSE_SCORE = 0;

    public function __construct(
        private StandingsCalculatorService $standings,
        private PlayerStatsCalculatorService $playerStats,
        private FixtureGeneratorService $fixture,
        private \App\Services\Torneos\PlayerCareerStatsService $careerStats,
        private \App\Services\Social\FeedService $feed,
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

        // Convocatoria previa (Limitación #6): si el capitán ya convocó y algunos
        // jugadores confirmaron asistencia, la planilla arranca pre-marcada con
        // esos jugadores en vez de todo el plantel activo.
        $callUps = \App\Models\Torneos\MatchCallUp::where('match_id', $match->id)->get();
        $confirmedCallUpIds = $callUps->where('status', 'confirmado')->pluck('team_player_id');
        $teamsWithCallUps = $callUps->pluck('team_id')->unique();

        // La planilla solo es editable si el partido está programado o en vivo.
        // Un partido finalizado se consulta/exporta; para corregirlo hay que anularlo.
        $canEdit = $match->isScheduled() || $match->isLive();

        // MVP: solo si el torneo usa esa metodología.
        $mvpEnabled = $tournament->mvpEnabled();

        return view('admin.torneos.partidos.resultado', compact(
            'tournament', 'match', 'homePlayers', 'awayPlayers', 'statsConfig', 'existingLineups',
            'confirmedCallUpIds', 'teamsWithCallUps', 'canEdit', 'mvpEnabled'
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
            // W.O. (un equipo no se presentó).
            'is_walkover'                   => ['nullable', 'boolean'],
            'walkover_winner'               => ['nullable', 'in:home,away'],
            // Cuerpo arbitral (solo principal, secundario y observaciones).
            'referee'                       => ['nullable', 'string', 'max:120'],
            'second_referee'                => ['nullable', 'string', 'max:120'],
            'referee_notes'                 => ['nullable', 'string', 'max:1000'],
            // Penales: definen el ganador en empates de eliminatoria.
            'home_penalties'                => ['nullable', 'integer', 'min:0', 'max:99'],
            'away_penalties'                => ['nullable', 'integer', 'min:0', 'max:99'],
            // Figura del partido (MVP), opcional.
            'mvp_team_player_id'            => ['nullable', 'integer', 'exists:team_players,id'],
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
            // Minuto opcional: en fútbol amateur no siempre se registra.
            'events.*.minute'               => ['nullable', 'integer', 'min:1', 'max:120'],
        ]);

        // ── Rama W.O.: hay ganador pero no se cargan goles a jugadores ──────────
        if (filter_var($data['is_walkover'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return $this->storeWalkover($request, $tournament, $match, $data);
        }

        // ── Resultado normal ────────────────────────────────────────────────────
        // El marcador se construye en el formulario a partir de los goles de los
        // jugadores (cada gol es un evento). El backend confía en ese marcador,
        // que llega ya consistente con la sumatoria de goles.
        $homeScore = (int) $data['home_score'];
        $awayScore = (int) $data['away_score'];

        // En empate, los penales (si se cargaron) definen el ganador — necesario
        // para que la eliminatoria pueda avanzar.
        $homePens = isset($data['home_penalties']) && $data['home_penalties'] !== null ? (int) $data['home_penalties'] : null;
        $awayPens = isset($data['away_penalties']) && $data['away_penalties'] !== null ? (int) $data['away_penalties'] : null;

        $winnerId = match (true) {
            $homeScore > $awayScore => $match->home_team_id,
            $awayScore > $homeScore => $match->away_team_id,
            $homePens !== null && $awayPens !== null && $homePens > $awayPens => $match->home_team_id,
            $homePens !== null && $awayPens !== null && $awayPens > $homePens => $match->away_team_id,
            default                  => null,
        };

        $sheet = $this->extractSheet($request);

        DB::transaction(function () use ($match, $homeScore, $awayScore, $winnerId, $homePens, $awayPens, $data, $sheet, $tournament) {
            // 1-3. Actualizar partido + planilla oficial
            $match->is_walkover    = false;
            $match->home_score     = $homeScore;
            $match->away_score     = $awayScore;
            $match->winner_team_id = $winnerId;
            // MVP solo si el torneo habilita la metodología; si no, se ignora.
            $match->mvp_team_player_id = $tournament->mvpEnabled() ? ($data['mvp_team_player_id'] ?? null) : null;
            $match->status         = 'finished';

            // Cuerpo arbitral (principal, secundario, observaciones).
            $match->referee        = $data['referee'] ?? null;
            $match->second_referee = $data['second_referee'] ?? null;
            $match->referee_notes  = $data['referee_notes'] ?? null;
            // Roles retirados de la planilla: se limpian si quedaban de un guardado previo.
            $match->third_referee  = null;
            $match->timekeeper     = null;
            $match->coordinator    = null;

            // Periodos retirados de la planilla; los penales se conservan para desempate.
            $match->home_score_ht  = null;
            $match->away_score_ht  = null;
            $match->home_score_et  = null;
            $match->away_score_et  = null;
            $match->home_penalties = $homePens;
            $match->away_penalties = $awayPens;

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
                    'minute'         => isset($ev['minute']) && $ev['minute'] !== '' ? (int) $ev['minute'] : null,
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

            // 7. Recalcular player_stats para ambos equipos + consolidar acumulado histórico
            if ($match->home_team_id) {
                $home = Team::find($match->home_team_id);
                $this->playerStats->recalculate($tournament, $home);
                $this->careerStats->refreshForTeam($home);
            }
            if ($match->away_team_id) {
                $away = Team::find($match->away_team_id);
                $this->playerStats->recalculate($tournament, $away);
                $this->careerStats->refreshForTeam($away);
            }

            // 8. Si todos los partidos de la fase están finished, marcar fase completada
            $this->maybeCompletePhase($phase);
        });

        // Feed (post-commit, no bloqueante): resultado de torneo para los
        // seguidores de cualquiera de los dos clubs.
        $this->recordTournamentResultFeed($tournament, $match);

        return redirect()
            ->route('admin.torneos.partidos.index', $tournament)
            ->with('status', "Resultado del partido #{$match->match_number} guardado correctamente.");
    }

    /**
     * Registra un partido ganado por W.O. (un equipo no se presentó):
     * asigna un marcador convencional y un ganador, sin convocatoria ni eventos.
     */
    private function storeWalkover(Request $request, Tournament $tournament, TournamentMatch $match, array $data): RedirectResponse
    {
        $side = $data['walkover_winner'] ?? null;

        if (! in_array($side, ['home', 'away'], true)) {
            return back()->withInput()
                ->with('error', 'Indicá qué equipo se presentó para registrar el W.O.');
        }

        if (! $match->home_team_id || ! $match->away_team_id) {
            return back()->with('error', 'No se puede registrar W.O. sin ambos equipos definidos.');
        }

        $win  = self::WALKOVER_WIN_SCORE;
        $lose = self::WALKOVER_LOSE_SCORE;

        $homeScore = $side === 'home' ? $win : $lose;
        $awayScore = $side === 'home' ? $lose : $win;
        $winnerId  = $side === 'home' ? $match->home_team_id : $match->away_team_id;

        $sheet = $this->extractSheet($request);

        DB::transaction(function () use ($match, $homeScore, $awayScore, $winnerId, $data, $sheet, $tournament) {
            $match->is_walkover    = true;
            $match->home_score     = $homeScore;
            $match->away_score     = $awayScore;
            $match->winner_team_id = $winnerId;
            $match->status         = 'finished';

            $match->referee        = $data['referee'] ?? null;
            $match->second_referee = $data['second_referee'] ?? null;
            $match->referee_notes  = $data['referee_notes'] ?? null;
            $match->third_referee  = null;
            $match->timekeeper     = null;
            $match->coordinator    = null;
            $match->home_score_ht  = null;
            $match->away_score_ht  = null;
            $match->home_score_et  = null;
            $match->away_score_et  = null;
            $match->home_penalties = null;
            $match->away_penalties = null;
            $match->match_sheet    = $sheet;
            $match->save();

            // W.O.: no hay convocatoria ni eventos de jugadores.
            $match->lineups()->delete();
            $match->events()->delete();

            $phase = $match->phase;
            if ($phase->isGroups()) {
                $this->standings->recalculate($phase);
            }
            if ($match->home_team_id) {
                $home = Team::find($match->home_team_id);
                $this->playerStats->recalculate($tournament, $home);
                $this->careerStats->refreshForTeam($home);
            }
            if ($match->away_team_id) {
                $away = Team::find($match->away_team_id);
                $this->playerStats->recalculate($tournament, $away);
                $this->careerStats->refreshForTeam($away);
            }

            $this->maybeCompletePhase($phase);
        });

        $this->recordTournamentResultFeed($tournament, $match);

        return redirect()
            ->route('admin.torneos.partidos.index', $tournament)
            ->with('status', "Partido #{$match->match_number} registrado como W.O.");
    }

    /**
     * Feed: resultado de torneo. Conecta los clubs de ambos equipos
     * (actor=local, subject=visitante) y se distribuye por la ciudad del torneo.
     * No bloqueante: nunca rompe el guardado del resultado.
     */
    private function recordTournamentResultFeed(Tournament $tournament, TournamentMatch $match): void
    {
        $homeTeam = $match->home_team_id ? Team::with('club')->find($match->home_team_id) : null;
        $awayTeam = $match->away_team_id ? Team::with('club')->find($match->away_team_id) : null;

        $this->feed->record(
            \App\Models\Social\FeedEvent::TYPE_RESULTADO_TORNEO,
            $homeTeam?->club,
            $awayTeam?->club,
            [
                'city'    => $tournament->city,
                'payload' => [
                    'tournament_id'    => $tournament->id,
                    'tournament_name'  => $tournament->name,
                    'tournament_match_id' => $match->id,
                    'home'             => $homeTeam?->club?->name ?? $homeTeam?->name,
                    'away'             => $awayTeam?->club?->name ?? $awayTeam?->name,
                    'home_score'       => $match->home_score,
                    'away_score'       => $match->away_score,
                ],
            ]
        );
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

        if ($match->phase->type === 'knockout' && $match->winner_team_id) {
            $downstream = collect($this->fixture->downstreamMatchesFor($match))
                ->first(fn ($m) => $m->home_team_id || $m->away_team_id);

            if ($downstream) {
                return back()->with('error', "No se puede anular: el partido #{$downstream->match_number} de la ronda siguiente ya tiene un equipo asignado. Anulá primero ese resultado.");
            }
        }

        DB::transaction(function () use ($match, $tournament) {
            $match->lineups()->delete();
            $match->events()->delete();
            $match->home_score     = null;
            $match->away_score     = null;
            $match->winner_team_id = null;
            $match->mvp_team_player_id = null;
            $match->is_walkover    = false;
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
                $home = Team::find($match->home_team_id);
                $this->playerStats->recalculate($tournament, $home);
                $this->careerStats->refreshForTeam($home);
            }
            if ($match->away_team_id) {
                $away = Team::find($match->away_team_id);
                $this->playerStats->recalculate($tournament, $away);
                $this->careerStats->refreshForTeam($away);
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

                    // Consolidación final: acumulados + fair play + logros + ranking (Sesión F).
                    app(\App\Services\Torneos\ReputationService::class)->consolidateTournament($tournament);
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
     * Normaliza los datos por equipo del acta (delegado, faltas acumulativas por
     * tiempo y firma del capitán) en una estructura JSON segura.
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
                'delegate'       => $this->cleanString($s['delegate'] ?? null),
                'fouls_1'        => $this->boundedInt($s['fouls_1'] ?? null),
                'fouls_2'        => $this->boundedInt($s['fouls_2'] ?? null),
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
