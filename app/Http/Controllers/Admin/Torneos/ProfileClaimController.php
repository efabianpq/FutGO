<?php

namespace App\Http\Controllers\Admin\Torneos;

use App\Exceptions\Torneos\ProfileClaimException;
use App\Http\Controllers\Controller;
use App\Models\Torneos\ProfileClaim;
use App\Services\Torneos\ProfileClaimService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reclamos de perfil ESCALADOS a la plataforma (Limitación #2): cuando el club
 * no tiene capitán activo, un admin resuelve el reclamo (aprobar o rechazar).
 */
class ProfileClaimController extends Controller
{
    public function __construct(private ProfileClaimService $claims)
    {
    }

    /** Bandeja de reclamos escalados + historial reciente de resueltos. */
    public function index(): View
    {
        $escalated = $this->claims->escalatedForAdmin()
            ->map(fn (ProfileClaim $claim) => [
                'claim'       => $claim,
                'tournaments' => $claim->clubPlayer
                    ? $this->claims->tournamentsForClubPlayer($claim->clubPlayer)
                    : collect(),
            ]);

        $resolved = ProfileClaim::whereIn('status', ['approved', 'rejected'])
            ->with(['user', 'club', 'resolver'])
            ->latest('resolved_at')
            ->limit(30)
            ->get();

        return view('admin.torneos.reclamos.index', compact('escalated', 'resolved'));
    }

    public function approve(ProfileClaim $claim): RedirectResponse
    {
        try {
            $this->claims->approve($claim, auth()->user());
        } catch (ProfileClaimException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Reclamo aprobado: el jugador quedó vinculado y heredó su historial.');
    }

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
