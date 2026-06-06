<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\Team;
use App\Models\Torneos\Tournament;
use App\Models\Torneos\TournamentMatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Convocatoria PREVIA al partido: el capitán arma la lista y cada jugador
 * confirma o declina su asistencia. Es planeación; no toca estadísticas.
 */
class CallUpController extends Controller
{
    /** Pantalla de gestión de la convocatoria (solo el capitán del equipo). */
    public function manage(Tournament $tournament, TournamentMatch $match): View|RedirectResponse
    {
        $this->ensureBelongs($tournament, $match);
        $team = $this->captainTeamIn($match);
        abort_unless($team !== null, 403, 'Solo el capitán de un equipo del partido puede armar la convocatoria.');

        // Plantilla disponible: jugadores activos del equipo en este torneo.
        $players = $team->players()->where('status', 'active')->orderBy('jersey_number')->with('user')->get();

        $callUps = MatchCallUp::where('match_id', $match->id)
            ->where('team_id', $team->id)
            ->get()
            ->keyBy('team_player_id');

        $match->load(['homeTeam', 'awayTeam', 'phase']);

        return view('torneos.convocatoria.manage', compact('tournament', 'match', 'team', 'players', 'callUps'));
    }

    /** Guarda la convocatoria (conjunto de jugadores convocados). */
    public function store(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureBelongs($tournament, $match);
        $team = $this->captainTeamIn($match);
        abort_unless($team !== null, 403);

        if ($match->isFinished()) {
            return back()->with('error', 'El partido ya finalizó: la convocatoria es solo para partidos por jugar.');
        }

        $data = $request->validate([
            'player_ids'   => ['array'],
            'player_ids.*' => ['integer'],
        ]);

        $desired   = collect($data['player_ids'] ?? [])->map(fn ($id) => (int) $id);
        $validIds  = $team->players()->where('status', 'active')->pluck('id');
        $desired   = $desired->intersect($validIds)->values();

        $existing  = MatchCallUp::where('match_id', $match->id)->where('team_id', $team->id)->get()->keyBy('team_player_id');

        // Quitar los desconvocados.
        $existing->keys()->reject(fn ($id) => $desired->contains($id))->each(function ($id) use ($match, $team) {
            MatchCallUp::where('match_id', $match->id)->where('team_id', $team->id)->where('team_player_id', $id)->delete();
        });

        // Agregar los nuevos (los ya convocados mantienen su confirmación).
        foreach ($desired as $tpId) {
            if (! $existing->has($tpId)) {
                MatchCallUp::create([
                    'match_id'       => $match->id,
                    'team_player_id' => $tpId,
                    'team_id'        => $team->id,
                    'status'         => 'convocado',
                ]);
            }
        }

        return back()->with('status', 'Convocatoria actualizada (' . $desired->count() . ' jugadores).');
    }

    /** El jugador confirma o declina su propia convocatoria. */
    public function respond(Request $request, Tournament $tournament, TournamentMatch $match): RedirectResponse
    {
        $this->ensureBelongs($tournament, $match);

        $data = $request->validate([
            'response' => ['required', 'in:confirmado,declinado'],
        ]);

        $callUp = MatchCallUp::where('match_id', $match->id)
            ->whereHas('teamPlayer', fn ($q) => $q->where('user_id', $request->user()->id))
            ->first();

        abort_unless($callUp !== null, 403, 'No estás convocado a este partido.');

        if ($match->isFinished()) {
            return back()->with('error', 'El partido ya finalizó.');
        }

        $callUp->update(['status' => $data['response'], 'responded_at' => now()]);

        $msg = $data['response'] === 'confirmado' ? 'Confirmaste tu asistencia.' : 'Declinaste la convocatoria.';
        return back()->with('status', $msg);
    }

    // ─────────────────────────────────────────────────────────────────

    /** Equipo del partido cuyo capitán es el usuario autenticado, o null. */
    private function captainTeamIn(TournamentMatch $match): ?Team
    {
        $uid = auth()->id();
        foreach ([$match->home_team_id, $match->away_team_id] as $teamId) {
            if (! $teamId) {
                continue;
            }
            $team = Team::find($teamId);
            if ($team && $team->captain_user_id === $uid) {
                return $team;
            }
        }
        return null;
    }

    private function ensureBelongs(Tournament $tournament, TournamentMatch $match): void
    {
        abort_unless($match->phase->tournament_id === $tournament->id, 404);
    }
}
