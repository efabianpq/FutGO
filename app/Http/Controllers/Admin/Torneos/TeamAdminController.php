<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\RosterMovement;
use App\Models\Torneos\Team;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\Tournament;
use App\Models\User;
use App\Services\Torneos\ClubMembershipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TeamAdminController extends Controller
{
    public function index(Tournament $tournament): View
    {
        $this->authorizeAccess($tournament);

        $teams = $tournament->teams()
            ->withCount('players')
            ->with(['captain', 'club'])
            ->orderBy('status')
            ->orderBy('name')
            ->get();

        return view('admin.torneos.equipos.index', compact('tournament', 'teams'));
    }

    public function show(Tournament $tournament, Team $team): View
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $team->load(['captain', 'players.user']);

        // Otros equipos del torneo (para transferencias con torneo en inscripción).
        $otherTeams = $tournament->teams()
            ->where('id', '!=', $team->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.torneos.equipos.show', compact('tournament', 'team', 'otherTeams'));
    }

    /**
     * H7: el ADMIN del torneo crea un equipo directamente (para equipos que no
     * usan la app). Crea un Club PERMANENTE y lo inscribe en el torneo.
     *
     * - Si se indica el email de un capitán existente: el club queda 'validado'
     *   con ese usuario como capitán.
     * - Si no: el club queda 'por_validar' (sin capitán) hasta que se le asigne uno.
     */
    public function createClub(Request $request, Tournament $tournament): RedirectResponse
    {
        $this->authorizeAccess($tournament);

        if (! in_array($tournament->status, ['open', 'draft'], true)) {
            return back()->with('error', 'Solo se pueden crear equipos con el torneo en inscripción.');
        }

        $data = $request->validate([
            'name'          => ['required', 'string', 'min:3', 'max:80'],
            'color'         => ['nullable', 'string', 'max:20'],
            'captain_email' => ['nullable', 'email', 'max:120'],
        ], [
            'name.required' => 'Ingresa el nombre del equipo.',
        ]);

        // ¿El nombre ya está inscrito en este torneo?
        if ($tournament->teams()->where('name', $data['name'])->exists()) {
            return back()->withInput()->with('error', 'Ya hay un equipo con ese nombre en el torneo.');
        }

        // Resolver capitán (opcional) por email.
        $captain = null;
        if (! empty($data['captain_email'])) {
            $captain = User::where('email', $data['captain_email'])->first();
            if (! $captain) {
                return back()->withInput()->with('error', 'No existe un usuario con ese email. Puedes crear el equipo sin capitán y asignarlo luego.');
            }
        }

        DB::transaction(function () use ($data, $captain, $tournament) {
            $club = Club::create([
                'name'               => $data['name'],
                'slug'               => $this->uniqueClubSlug($data['name']),
                'color'              => $data['color'] ?? null,
                'created_by_user_id' => auth()->id(),
                'captain_user_id'    => $captain?->id,
                // Validado solo si tiene capitán (usuario del sistema); si no, por validar.
                'status'             => $captain ? 'validado' : 'por_validar',
            ]);

            // Si hay capitán, queda como miembro capitán de la plantilla permanente.
            if ($captain) {
                ClubPlayer::create([
                    'club_id'             => $club->id,
                    'user_id'             => $captain->id,
                    'is_captain'          => true,
                    'verification_status' => 'registrado',
                    'status'              => 'active',
                ]);
            }

            // Inscribir el club en el torneo (queda pending para aprobación).
            app(ClubMembershipService::class)->enroll($club, $tournament);
        });

        $msg = $captain
            ? "Equipo \"{$data['name']}\" creado e inscrito (capitán asignado)."
            : "Equipo \"{$data['name']}\" creado e inscrito. Queda POR VALIDAR hasta asignarle un capitán.";

        return back()->with('status', $msg);
    }

    /**
     * H7: asigna un capitán (usuario del sistema) a un club "por_validar",
     * dejándolo validado. Propaga el capitán a la inscripción del torneo.
     */
    public function assignCaptain(Request $request, Tournament $tournament, Team $team): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $club = $team->club;
        if (! $club) {
            return back()->with('error', 'Este equipo no tiene un club permanente asociado.');
        }
        if ($club->isValidado()) {
            return back()->with('error', 'Este equipo ya tiene un capitán asignado.');
        }

        $data = $request->validate([
            'captain_email' => ['required', 'email', 'max:120'],
        ], [
            'captain_email.required' => 'Ingresa el email del capitán.',
        ]);

        $captain = User::where('email', $data['captain_email'])->first();
        if (! $captain) {
            return back()->with('error', 'No existe un usuario registrado con ese email.');
        }

        DB::transaction(function () use ($club, $captain) {
            // Alta del capitán en la plantilla permanente si no estaba.
            $cp = $club->players()->where('user_id', $captain->id)->first();
            if (! $cp) {
                $cp = ClubPlayer::create([
                    'club_id'             => $club->id,
                    'user_id'             => $captain->id,
                    'is_captain'          => true,
                    'verification_status' => 'registrado',
                    'status'              => 'active',
                ]);
            } else {
                $club->players()->update(['is_captain' => false]);
                $cp->update(['is_captain' => true, 'status' => 'active']);
            }

            $club->update(['captain_user_id' => $captain->id, 'status' => 'validado']);

            // Propagar a las participaciones no finalizadas.
            app(ClubMembershipService::class)->changeCaptain($club, $captain);
        });

        return back()->with('status', "Capitán asignado. El equipo \"{$club->name}\" quedó validado.");
    }

    private function uniqueClubSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'equipo';
        $slug = $base;
        $i = 1;
        while (Club::where('slug', $slug)->exists()) {
            $i++;
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    public function approve(Tournament $tournament, Team $team): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $team->status = 'approved';
        $team->save();

        return back()->with('status', "Equipo \"{$team->name}\" aprobado.");
    }

    public function reject(Tournament $tournament, Team $team): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id, 404);

        $team->status = 'rejected';
        $team->save();

        return back()->with('status', "Equipo \"{$team->name}\" rechazado.");
    }

    /** Aprueba un jugador agregado a la plantilla con el torneo en curso. */
    public function approvePlayer(Tournament $tournament, Team $team, TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id && $teamPlayer->team_id === $team->id, 404);

        if (! $teamPlayer->isPending()) {
            return back()->with('error', 'Solo se pueden aprobar jugadores pendientes.');
        }

        $teamPlayer->update(['status' => 'active']);

        return back()->with('status', $teamPlayer->displayName() . ' aprobado para el torneo.');
    }

    /** Rechaza un jugador agregado a la plantilla con el torneo en curso. */
    public function rejectPlayer(Tournament $tournament, Team $team, TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id && $teamPlayer->team_id === $team->id, 404);

        if (! $teamPlayer->isPending()) {
            return back()->with('error', 'Solo se pueden rechazar jugadores pendientes.');
        }

        $teamPlayer->update(['status' => 'rejected']);

        return back()->with('status', $teamPlayer->displayName() . ' rechazado para el torneo.');
    }

    /**
     * Baja de un jugador durante el torneo: deja de participar (status inactive).
     * Conserva sus estadísticas de partidos ya jugados (player_stats no se borra)
     * y queda excluido de convocatorias y planillas futuras. Se registra en el historial.
     */
    public function releasePlayer(Request $request, Tournament $tournament, Team $team, TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id && $teamPlayer->team_id === $team->id, 404);

        if ($tournament->isFinished()) {
            return back()->with('error', 'El torneo ya finalizó: no se pueden dar bajas.');
        }
        if ($teamPlayer->isCaptain()) {
            return back()->with('error', 'No se puede dar de baja al capitán del equipo.');
        }
        if ($teamPlayer->status === 'inactive') {
            return back()->with('error', 'El jugador ya está dado de baja.');
        }

        $data = $request->validate(['notes' => ['nullable', 'string', 'max:255']]);

        DB::transaction(function () use ($teamPlayer, $team, $tournament, $data) {
            $teamPlayer->update(['status' => 'inactive']);

            RosterMovement::create([
                'tournament_id'    => $tournament->id,
                'team_player_id'   => $teamPlayer->id,
                'user_id'          => $teamPlayer->user_id,
                'player_name'      => $teamPlayer->displayName(),
                'type'             => 'baja',
                'from_team_id'     => $team->id,
                'to_team_id'       => null,
                'acted_by_user_id' => auth()->id(),
                'notes'            => $data['notes'] ?? null,
            ]);
        });

        return back()->with('status', $teamPlayer->displayName() . ' fue dado de baja. Sus estadísticas previas se conservan.');
    }

    /**
     * Cambio de equipo (transferencia) dentro del mismo torneo.
     * REGLA: solo permitido con el torneo en inscripción (status 'open'); una vez
     * 'in_progress' la integridad competitiva lo impide (la vía es baja + alta aprobada).
     */
    public function transferPlayer(Request $request, Tournament $tournament, Team $team, TeamPlayer $teamPlayer): RedirectResponse
    {
        $this->authorizeAccess($tournament);
        abort_unless($team->tournament_id === $tournament->id && $teamPlayer->team_id === $team->id, 404);

        if ($tournament->status !== 'open') {
            return back()->with('error', 'El cambio de equipo solo se permite con el torneo en inscripción.');
        }
        if ($teamPlayer->isCaptain()) {
            return back()->with('error', 'No se puede transferir al capitán del equipo.');
        }

        $data = $request->validate([
            'to_team_id' => ['required', 'integer'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $target = Team::where('id', $data['to_team_id'])->where('tournament_id', $tournament->id)->first();
        if (! $target || $target->id === $team->id) {
            return back()->with('error', 'Equipo destino inválido.');
        }

        // El jugador no puede estar ya en el equipo destino.
        if ($teamPlayer->user_id) {
            $dup = TeamPlayer::where('team_id', $target->id)->where('user_id', $teamPlayer->user_id)->exists();
            if ($dup) {
                return back()->with('error', 'El jugador ya pertenece al equipo destino.');
            }
        }

        DB::transaction(function () use ($teamPlayer, $team, $target, $tournament, $data) {
            $teamPlayer->update(['team_id' => $target->id, 'is_captain' => false, 'status' => 'active']);

            RosterMovement::create([
                'tournament_id'    => $tournament->id,
                'team_player_id'   => $teamPlayer->id,
                'user_id'          => $teamPlayer->user_id,
                'player_name'      => $teamPlayer->displayName(),
                'type'             => 'cambio',
                'from_team_id'     => $team->id,
                'to_team_id'       => $target->id,
                'acted_by_user_id' => auth()->id(),
                'notes'            => $data['notes'] ?? null,
            ]);
        });

        return back()->with('status', $teamPlayer->displayName() . " transferido a {$target->name}.");
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
}
