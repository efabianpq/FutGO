<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\MatchEvent;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\TournamentMatch;
use Illuminate\View\View;

/**
 * Portal del Jugador (/mi-actividad): vista centralizada de la actividad del usuario
 * como jugador en todos sus equipos y torneos: torneos, partidos, estadísticas y
 * situación disciplinaria.
 */
class PlayerDashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Un registro de jugador por cada equipo/torneo donde está inscrito.
        $teamPlayers = TeamPlayer::where('user_id', $user->id)
            ->with(['team.tournament'])
            ->get();

        $teamIds       = $teamPlayers->pluck('team_id');
        $teamPlayerIds = $teamPlayers->pluck('id');

        // ── Mis torneos ──────────────────────────────────────────────────────
        $tournaments = $teamPlayers
            ->map(fn ($tp) => $tp->team?->tournament)
            ->filter()
            ->unique('id')
            ->values();

        $activeTournaments   = $tournaments->whereNotIn('status', ['finished', 'cancelled'])->values();
        $finishedTournaments = $tournaments->whereIn('status', ['finished', 'cancelled'])->values();

        // ── Mis partidos ─────────────────────────────────────────────────────
        $matches = TournamentMatch::query()
            ->where(fn ($q) => $q
                ->whereIn('home_team_id', $teamIds)
                ->orWhereIn('away_team_id', $teamIds))
            ->with(['homeTeam', 'awayTeam', 'phase.tournament', 'group'])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderBy('match_number')
            ->get();

        $upcomingMatches = $matches->whereIn('status', ['scheduled', 'live'])->take(8)->values();
        $recentResults   = $matches->where('status', 'finished')->sortByDesc('match_number')->take(8)->values();

        // ── Mis estadísticas (agregadas en todos los torneos) ────────────────
        $stats = PlayerStat::whereIn('team_player_id', $teamPlayerIds)
            ->with(['tournament'])
            ->get();

        $totals = [
            'matches_played' => (int) $stats->sum('matches_played'),
            'goals'          => (int) $stats->sum('goals'),
            'assists'        => (int) $stats->sum('assists'),
            'yellow_cards'   => (int) $stats->sum('yellow_cards'),
            'red_cards'      => (int) $stats->sum('red_cards'),
            'minutes_played' => (int) $stats->sum('minutes_played'),
        ];

        // Solo estadísticas con algún partido jugado, para el detalle por torneo.
        $statsByTournament = $stats
            ->where('matches_played', '>', 0)
            ->sortByDesc('goals')
            ->values();

        // ── Mis sanciones ────────────────────────────────────────────────────
        // Suspensión vigente: el jugador quedó inactivo por roja en ese equipo.
        $activeSuspensions = $teamPlayers
            ->where('status', 'inactive')
            ->values();

        // Historial disciplinario completo (amarillas + rojas).
        $disciplinary = MatchEvent::whereIn('team_player_id', $teamPlayerIds)
            ->whereIn('type', ['yellow_card', 'red_card'])
            ->with(['match.homeTeam', 'match.awayTeam', 'match.phase.tournament', 'teamPlayer.team'])
            ->orderByDesc('id')
            ->get();

        return view('torneos.mi-actividad', compact(
            'user',
            'activeTournaments',
            'finishedTournaments',
            'upcomingMatches',
            'recentResults',
            'totals',
            'statsByTournament',
            'activeSuspensions',
            'disciplinary'
        ));
    }
}
