<?php

namespace App\Services\Support;

use App\Models\User;

class SupportContextBuilder
{
    /**
     * Construye el snapshot de contexto del usuario para el bot y los tickets.
     * NUNCA incluye email, teléfono, documento ni password.
     */
    public function buildForUser(User $user): array
    {
        // Rol efectivo. El modelo de usuario está unificado: solo existe `admin`
        // como rol especial; cualquier otro usuario es "general" (jugador o capitán
        // según pertenencia por club).
        $rol = match (true) {
            $user->role === 'admin'         => 'Administrador global',
            $user->captainClubs()->exists() => 'Capitán de equipo',
            default                         => 'Jugador',
        };

        // Torneos activos (últimos 3)
        $torneos = $user->teamPlayers()
            ->with(['team.tournament'])
            ->get()
            ->map(fn ($tp) => $tp->team?->tournament)
            ->filter()
            ->unique('id')
            ->take(3)
            ->map(fn ($t) => [
                'nombre'  => $t->name,
                'status'  => $t->status,
                'formato' => $t->format,
            ])->values()->toArray();

        // Clubs que capitanea
        $clubes = $user->captainClubs()
            ->take(3)
            ->get()
            ->map(fn ($c) => [
                'nombre' => $c->name,
                'nivel'  => $c->play_level,
                'ciudad' => $c->city ?? null,
            ])->toArray();

        // Oportunidades abiertas
        $oportunidades = \App\Models\Social\Opportunity::where('user_id', $user->id)
            ->where('status', 'abierta')
            ->take(3)
            ->pluck('type')
            ->toArray();

        // Amistosos pendientes de resultado (donde el usuario capitanea alguno de los clubes)
        $amistososPendientes = \App\Models\Social\FriendlyMatch::where('status', 'confirmado')
            ->where('scheduled_at', '<', now())
            ->where(function ($q) use ($user) {
                $q->whereHas('homeClub', fn ($c) => $c->where('captain_user_id', $user->id))
                  ->orWhereHas('awayClub', fn ($c) => $c->where('captain_user_id', $user->id));
            })
            ->count();

        return [
            'user_id'                => $user->id,
            'futgo_id'               => $user->futgo_id,
            'nombre'                 => $user->name,
            'rol'                    => $rol,
            'ciudad'                 => $user->city ?? 'no especificada',
            'nivel'                  => $user->play_level ?? 'no especificado',
            'torneos_activos'        => $torneos,
            'clubes_capitaneados'    => $clubes,
            'oportunidades_abiertas' => $oportunidades,
            'amistosos_pendientes_resultado' => $amistososPendientes,
            'confiabilidad'          => optional($user->reliabilityScore)->score,
            'timestamp'              => now()->toISOString(),
        ];
    }
}
