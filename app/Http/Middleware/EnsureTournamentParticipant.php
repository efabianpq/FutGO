<?php

namespace App\Http\Middleware;

use App\Models\Torneos\Tournament;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permite el acceso solo a quienes pertenecen al torneo:
 *   - Administrador global (role = admin)
 *   - Administrador del torneo (tournament_admins)
 *   - Capitán de un equipo del torneo
 *   - Jugador registrado en un equipo del torneo
 *
 * En cualquier otro caso responde 403.
 *
 * Reutilizable: depende únicamente del parámetro de ruta {tournament}.
 * Pensado para usarse junto a ['auth','ensure.active','ensure.module:torneos'].
 */
class EnsureTournamentParticipant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // El binding de ruta ya resolvió el modelo (SubstituteBindings corre antes).
        $tournament = $request->route('tournament');
        if (! $tournament instanceof Tournament) {
            $tournament = Tournament::find($tournament);
        }

        if (! $tournament) {
            abort(404);
        }

        if ($this->belongs($user, $tournament)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'No pertenecés a este torneo.'], 403);
        }

        abort(403, 'No pertenecés a este torneo.');
    }

    /** ¿El usuario pertenece al torneo en alguno de los roles permitidos? */
    private function belongs(?\App\Models\User $user, Tournament $tournament): bool
    {
        if (! $user) {
            return false;
        }

        // Administrador global ve todo.
        if ($user->isAdmin()) {
            return true;
        }

        // Administrador específico del torneo.
        if ($tournament->tournamentAdmins()->where('user_id', $user->id)->exists()) {
            return true;
        }

        // Capitán de un equipo del torneo.
        if ($tournament->teams()->where('captain_user_id', $user->id)->exists()) {
            return true;
        }

        // Jugador registrado en un equipo del torneo.
        return $tournament->teams()
            ->whereHas('players', fn ($q) => $q->where('user_id', $user->id))
            ->exists();
    }
}
