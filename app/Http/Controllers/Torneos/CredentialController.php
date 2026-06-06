<?php

namespace App\Http\Controllers\Torneos;

use App\Http\Controllers\Controller;
use App\Models\Torneos\TeamPlayer;
use App\Services\Torneos\CredentialService;
use Illuminate\View\View;

/**
 * Credencial digital del jugador (/torneos/credencial) — Sesión D.
 *
 * Muestra foto, nombre, identificador FUTGO y un QR único que los árbitros pueden
 * escanear (o ingresar el identificador a mano) para validar la identidad.
 */
class CredentialController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();

        // Resguardo: garantizar identificador aun si el usuario es anterior al hook.
        if (empty($user->futgo_id)) {
            $user->futgo_id = CredentialService::nextFutgoId();
            $user->save();
        }

        $qrSvg = CredentialService::qrSvgFor($user);

        // Equipos/torneos vigentes donde el jugador está activo (para la credencial).
        $memberships = TeamPlayer::where('user_id', $user->id)
            ->where('status', 'active')
            ->with(['team.tournament', 'team.club'])
            ->get()
            ->filter(fn ($tp) => $tp->team?->tournament
                && ! in_array($tp->team->tournament->status, ['cancelled'], true))
            ->values();

        return view('torneos.credencial.show', compact('user', 'qrSvg', 'memberships'));
    }
}
