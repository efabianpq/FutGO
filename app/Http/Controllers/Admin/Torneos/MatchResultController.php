<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\MatchLineup;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use App\Services\Torneos\PlayerStatsCalculatorService;
use App\Services\Torneos\StandingsCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MatchResultController extends Controller
{
    public function __construct(
        private StandingsCalculatorService $standings,
        private PlayerStatsCalculatorService $playerStats,
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

        return view('admin.torneos.partidos.resultado', compact(
            'tournament', 'match', 'homePlayers', 'awayPlayers', 'statsConfig', 'existingLineups'
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

        $winnerId = match (true) {
            $homeScore > $awayScore => $match->home_team_id,
            $awayScore > $homeScore => $match->away_team_id,
            default                  => null,
        };

        DB::transaction(function () use ($match, $homeScore, $awayScore, $winnerId, $data, $tournament) {
            // 1-3. Actualizar partido
            $match->home_score     = $homeScore;
            $match->away_score     = $awayScore;
            $match->winner_team_id = $winnerId;
            $match->status         = 'finished';
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

        if ($total > 0 && $total === $finished) {
            $phase->is_active = false;
            $phase->save();
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
}
