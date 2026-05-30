<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeamController extends Controller
{
    public function inscribir(Tournament $tournament): View|RedirectResponse
    {
        if (! $tournament->isOpen()) {
            return redirect()
                ->route('torneos.index')
                ->with('error', 'Este torneo no está aceptando inscripciones.');
        }

        // Si el usuario ya tiene equipo en este torneo, redirigir al panel
        if ($this->userTeamIn($tournament)) {
            return redirect()->route('torneos.equipo.show', $tournament);
        }

        return view('torneos.equipo.inscribir', compact('tournament'));
    }

    public function store(Request $request, Tournament $tournament): RedirectResponse
    {
        if (! $tournament->isOpen()) {
            return redirect()
                ->route('torneos.index')
                ->with('error', 'Este torneo no está aceptando inscripciones.');
        }

        // Un usuario no puede ser capitán de dos equipos en el mismo torneo
        if ($tournament->teams()->where('captain_user_id', $request->user()->id)->exists()) {
            return back()->with('error', 'Ya sos capitán de un equipo en este torneo.');
        }

        // Un usuario no puede estar en dos equipos del mismo torneo
        if ($this->userAlreadyInTournament($request->user(), $tournament)) {
            return back()->with('error', 'Ya pertenecés a un equipo en este torneo.');
        }

        $data = $request->validate([
            'name'  => [
                'required', 'string', 'min:3', 'max:80',
                Rule::unique('teams')->where('tournament_id', $tournament->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], [
            'name.unique' => 'Ya existe un equipo con ese nombre en este torneo.',
            'color.regex' => 'El color debe estar en formato hexadecimal (#RRGGBB).',
        ]);

        $team = Team::create([
            'tournament_id'   => $tournament->id,
            'captain_user_id' => $request->user()->id,
            'name'            => $data['name'],
            'color'           => $data['color'] ?? null,
            'status'          => 'pending',
        ]);

        // El capitán queda automáticamente registrado como jugador activo
        TeamPlayer::create([
            'team_id' => $team->id,
            'user_id' => $request->user()->id,
            'status'  => 'active',
        ]);

        return redirect()
            ->route('torneos.equipo.show', $tournament)
            ->with('status', 'Equipo inscripto. Esperá la aprobación del organizador.');
    }

    public function addPlayer(Request $request, Tournament $tournament): RedirectResponse
    {
        if (! $tournament->isOpen()) {
            return back()->with('error', 'No se pueden agregar jugadores: el torneo no está en inscripción.');
        }

        $team = $this->userTeamIn($tournament);

        if (! $team || $team->captain_user_id !== $request->user()->id) {
            abort(403);
        }

        $data = $request->validate([
            'email'          => ['required', 'email'],
            'jersey_number'  => ['nullable', 'integer', 'min:1', 'max:99'],
            'position'       => ['nullable', 'string', 'max:30'],
        ]);

        $player = User::where('email', $data['email'])->first();

        if (! $player) {
            return back()->withErrors([
                'email' => 'No existe ningún usuario registrado con ese email. Pedile que se registre primero en la plataforma.',
            ]);
        }

        // No se puede agregar a alguien que ya está en un equipo de este torneo
        if ($this->userAlreadyInTournament($player, $tournament)) {
            return back()->withErrors([
                'email' => "{$player->name} ya pertenece a un equipo en este torneo.",
            ]);
        }

        TeamPlayer::create([
            'team_id'        => $team->id,
            'user_id'        => $player->id,
            'jersey_number'  => $data['jersey_number'] ?? null,
            'position'       => $data['position'] ?? null,
            'status'         => 'active',
        ]);

        return back()->with('status', "{$player->name} agregado al equipo.");
    }

    public function removePlayer(Tournament $tournament, TeamPlayer $teamPlayer): RedirectResponse
    {
        $team = $this->userTeamIn($tournament);

        // Verificar que el teamPlayer pertenece al equipo del capitán autenticado
        if (! $team || $team->captain_user_id !== auth()->id() || $teamPlayer->team_id !== $team->id) {
            abort(403);
        }

        // No se puede quitar al propio capitán del equipo
        if ($teamPlayer->user_id === $team->captain_user_id) {
            return back()->with('error', 'El capitán no puede ser quitado del equipo.');
        }

        $teamPlayer->delete();

        return back()->with('status', 'Jugador quitado del equipo.');
    }

    // ─────────────────────────────────────────────────────────────────

    /** Retorna el equipo del usuario autenticado en este torneo, o null. */
    private function userTeamIn(Tournament $tournament): ?Team
    {
        return Team::where('tournament_id', $tournament->id)
            ->whereHas('players', fn ($q) => $q->where('user_id', auth()->id()))
            ->first();
    }

    /** Verifica si un usuario ya pertenece a algún equipo en este torneo. */
    private function userAlreadyInTournament(User $user, Tournament $tournament): bool
    {
        return TeamPlayer::whereHas(
            'team',
            fn ($q) => $q->where('tournament_id', $tournament->id)
        )->where('user_id', $user->id)->exists();
    }
}
