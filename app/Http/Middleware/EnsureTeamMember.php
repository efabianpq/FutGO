<?php

namespace App\Http\Middleware;

use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe el acceso a recursos de un equipo concreto.
 *
 * El equipo objetivo se deriva del parámetro de ruta disponible:
 *   - {player}  (TeamPlayer)  → el equipo del jugador
 *   - {team}    (Team)        → el equipo directamente
 *
 * Permite el acceso solo a:
 *   - Administrador global (role = admin)
 *   - Administrador del torneo (tournament_admins)
 *   - Capitán del equipo
 *   - Jugador (cualquier estado) registrado en el equipo
 *
 * Garantiza además que el equipo pertenezca al {tournament} de la ruta.
 * Un capitán/jugador no puede operar sobre equipos ajenos: responde 403.
 */
class EnsureTeamMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user       = $request->user();
        $tournament = $this->routeTournament($request);
        $team       = $this->resolveTeam($request);

        if (! $team) {
            abort(404);
        }

        // El equipo debe pertenecer al torneo de la ruta (evita cruces de IDs).
        if ($tournament && $team->tournament_id !== $tournament->id) {
            abort(404);
        }

        if ($this->isMember($user, $team)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'No pertenecés a este equipo.'], 403);
        }

        abort(403, 'No pertenecés a este equipo.');
    }

    private function routeTournament(Request $request): ?Tournament
    {
        $tournament = $request->route('tournament');

        if ($tournament instanceof Tournament) {
            return $tournament;
        }

        return $tournament ? Tournament::find($tournament) : null;
    }

    private function resolveTeam(Request $request): ?Team
    {
        $player = $request->route('player');
        if ($player instanceof TeamPlayer) {
            return $player->team;
        }
        if ($player !== null) {
            return optional(TeamPlayer::find($player))->team;
        }

        $team = $request->route('team');
        if ($team instanceof Team) {
            return $team;
        }
        if ($team !== null) {
            return Team::find($team);
        }

        return null;
    }

    private function isMember(?\App\Models\User $user, Team $team): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Administrador del torneo dueño del equipo.
        if ($team->tournament->tournamentAdmins()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Capitán del equipo.
        if ($team->captain_user_id === $user->id) {
            return true;
        }

        // Jugador registrado en el equipo (cualquier estado).
        return $team->players()->where('user_id', $user->id)->exists();
    }
}
