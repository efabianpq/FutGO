<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\TournamentMatch;
use App\Services\Social\FriendlyMatchService;
use App\Services\Social\PlayedWithService;
use App\Services\Torneos\PlayerCareerStatsService;
use Illuminate\View\View;

/**
 * Hoja de vida deportiva del jugador (/torneos/mi-carrera).
 *
 * Trayectoria permanente across torneos: acumulado total (lectura O(1) desde
 * player_career_stats), Mis torneos, Mis equipos y Mi historial (detalle por torneo).
 */
class PlayerCareerController extends Controller
{
    public function __construct(
        private PlayerCareerStatsService $career,
        private FriendlyMatchService $friendlies,
        private PlayedWithService $playedWith,
    ) {}

    public function show(): View
    {
        $user = auth()->user();

        // Acumulado: lectura O(1). Si nunca se consolidó, se construye al vuelo.
        $careerStat = $user->careerStat ?? $this->career->refreshForUser($user);

        // Inscripciones del jugador (un team_player por equipo/torneo).
        $teamPlayers = TeamPlayer::where('user_id', $user->id)
            ->with(['team.tournament', 'team.club'])
            ->get();

        $teamPlayerIds = $teamPlayers->pluck('id');

        // Detalle por torneo (Mi historial): stats con join al torneo y equipo.
        $statsByTournament = PlayerStat::whereIn('team_player_id', $teamPlayerIds)
            ->with(['tournament', 'teamPlayer.team.club'])
            ->get()
            ->sortByDesc(fn ($s) => $s->tournament?->created_at)
            ->values();

        // Mis torneos (únicos, con su equipo en cada uno).
        $tournaments = $teamPlayers
            ->filter(fn ($tp) => $tp->team?->tournament)
            ->map(fn ($tp) => [
                'tournament' => $tp->team->tournament,
                'team'       => $tp->team,
            ])
            ->unique(fn ($row) => $row['tournament']->id)
            ->values();

        // Mis equipos/clubes (identidad permanente, únicos por club o por equipo).
        $clubs = $teamPlayers
            ->filter(fn ($tp) => $tp->team)
            ->map(fn ($tp) => $tp->team)
            ->unique(fn ($team) => $team->club_id ?? 'team-' . $team->id)
            ->values();

        // ── Actividad (unifica el antiguo "Mi Actividad") ─────────────────────
        $teamIds = $teamPlayers->pluck('team_id');

        $matches = TournamentMatch::query()
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->with(['homeTeam', 'awayTeam', 'phase.tournament'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get();

        $upcomingMatches = $matches->whereIn('status', ['scheduled', 'live'])->take(8)->values();
        $recentResults   = $matches->where('status', 'finished')->sortByDesc('match_number')->take(8)->values();

        // Convocatorias del jugador para sus próximos partidos (confirmar/declinar).
        $myCallUps = \App\Models\Torneos\MatchCallUp::whereIn('match_id', $upcomingMatches->pluck('id'))
            ->whereIn('team_player_id', $teamPlayerIds)
            ->get()
            ->keyBy('match_id');

        $activeSuspensions = $teamPlayers->where('status', 'inactive')->values();

        $disciplinary = MatchEvent::whereIn('team_player_id', $teamPlayerIds)
            ->whereIn('type', ['yellow_card', 'red_card'])
            ->with(['match.homeTeam', 'match.awayTeam', 'match.phase.tournament'])
            ->orderByDesc('id')
            ->get();

        // ── Reputación (Sesión F): fair play ──
        // H15: la sección de Logros se retiró de la UI (datos en BD se conservan).
        // H14: "Mi historial" e "Historial por temporada" se consolidaron en una
        // sola sección que agrupa statsByTournament por año (en la vista).
        $fairPlay = $user->fairPlayScore;

        // H16: credencial como modal dentro de Mi Carrera (QR + identificador FUTGO).
        $credentialQrSvg = \App\Services\Torneos\CredentialService::qrSvgFor($user);

        // ── FutGO Social (S1-C): amistosos + métricas sociales ────────────────
        $friendlies     = $this->friendlies->userFriendlies($user);
        $socialMetrics  = $this->friendlies->userMetrics($user, $careerStat);

        // ── FutGO Social (S2-A): "Jugué con vos" — jugadores con quienes compartió
        // cancha (derivado, sin tabla propia). Top por cantidad de partidos.
        $playedWith = $this->playedWith->sharedPlayers($user)->take(12);

        return view('torneos.mi-carrera', compact(
            'user', 'careerStat', 'statsByTournament', 'tournaments', 'clubs',
            'upcomingMatches', 'recentResults', 'activeSuspensions', 'disciplinary', 'myCallUps',
            'fairPlay', 'credentialQrSvg', 'friendlies', 'socialMetrics', 'playedWith'
        ));
    }
}
