<?php

namespace App\Http\Controllers\Torneos;

use App\Exceptions\Torneos\ProfileClaimException;
use App\Http\Controllers\Controller;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\ProfileClaim;
use App\Services\Torneos\ProfileClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reclamo de perfil — lado del JUGADOR y del CAPITÁN (Limitación #2).
 *
 * - Jugador: ve los registros 'por_verificar' que coinciden con su documento,
 *   inicia un reclamo y sigue su estado (`index`, `store`, `escalate`).
 * - Capitán: bandeja de reclamos pendientes de sus clubs y resolución
 *   (`approvals`, `approve`, `reject`).
 */
class ProfileClaimController extends Controller
{
    public function __construct(private ProfileClaimService $claims)
    {
    }

    /** Vista del jugador: candidatos a reclamar + mis reclamos en curso. */
    public function index(): View
    {
        $user = auth()->user();

        $candidates = $this->claims->findCandidatesFor($user)
            ->map(fn (ClubPlayer $cp) => [
                'clubPlayer'  => $cp,
                'club'        => $cp->club,
                'tournaments' => $this->claims->tournamentsForClubPlayer($cp),
            ]);

        $myClaims = ProfileClaim::where('user_id', $user->id)
            ->with(['club', 'clubPlayer'])
            ->latest()
            ->get();

        return view('torneos.reclamos.index', compact('candidates', 'myClaims'));
    }

    /** El jugador inicia un reclamo sobre un registro candidato. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'club_player_id' => ['required', 'integer', 'exists:club_players,id'],
        ]);

        $clubPlayer = ClubPlayer::with('club')->findOrFail($data['club_player_id']);

        try {
            $claim = $this->claims->claim(auth()->user(), $clubPlayer);
        } catch (ProfileClaimException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', $claim->isEscalated()
            ? 'Reclamo enviado. El equipo no tiene capitán activo, así que lo revisará un administrador.'
            : 'Reclamo enviado. El capitán del equipo debe aprobarlo.');
    }

    /** El jugador escala un reclamo pendiente que el capitán no responde. */
    public function escalate(ProfileClaim $claim): RedirectResponse
    {
        abort_unless($claim->user_id === auth()->id(), 403);

        try {
            $this->claims->escalate($claim);
        } catch (ProfileClaimException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Reclamo escalado a la plataforma.');
    }

    // ─── Lado del CAPITÁN ──────────────────────────────────────────────────

    /** Bandeja de reclamos pendientes de los clubs que capitanea el usuario. */
    public function approvals(): View
    {
        $user = auth()->user();

        $pending = $this->claims->pendingForCaptain($user)
            ->map(fn (ProfileClaim $claim) => [
                'claim'       => $claim,
                'tournaments' => $claim->clubPlayer
                    ? $this->claims->tournamentsForClubPlayer($claim->clubPlayer)
                    : collect(),
            ]);

        return view('torneos.reclamos.aprobaciones', compact('pending'));
    }

    /** El capitán aprueba un reclamo → vincula y transfiere historial. */
    public function approve(ProfileClaim $claim): RedirectResponse
    {
        try {
            $this->claims->approve($claim, auth()->user());
        } catch (ProfileClaimException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Reclamo aprobado: el jugador quedó vinculado y heredó su historial.');
    }

    /** El capitán rechaza un reclamo → el registro queda sin cambios. */
    public function reject(Request $request, ProfileClaim $claim): RedirectResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->claims->reject($claim, auth()->user(), $data['note'] ?? null);
        } catch (ProfileClaimException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Reclamo rechazado.');
    }
}
