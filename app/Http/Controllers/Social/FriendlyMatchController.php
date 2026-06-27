<?php

namespace App\Http\Controllers\Social;

use App\Exceptions\Social\FriendlyMatchException;
use App\Http\Controllers\Controller;
use App\Models\Social\FriendlyMatch;
use App\Models\Torneos\Club;
use App\Services\Social\FriendlyMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FutGO Social — Fase 1 · Sesión S1-C · "Mis amistosos" (capitán).
 *
 * Carga independiente de resultado (doble confirmación), escalamiento de
 * disputa y cancelación. Solo el capitán de un equipo participante puede actuar
 * sobre el amistoso, y siempre en nombre de su propio equipo.
 */
class FriendlyMatchController extends Controller
{
    public function __construct(private FriendlyMatchService $service) {}

    /** "Mis amistosos": confirmados (pendientes de resultado), en disputa y jugados. */
    public function index(Request $request): View
    {
        $user    = $request->user();
        $clubIds = Club::where('captain_user_id', $user->id)->pluck('id')->all();

        $matches = $this->service->forClubs($clubIds);

        $pendientes = $matches->where('status', FriendlyMatch::STATUS_CONFIRMADO)->values();
        $enDisputa  = $matches->where('status', FriendlyMatch::STATUS_EN_DISPUTA)->values();
        $jugados    = $matches->where('status', FriendlyMatch::STATUS_JUGADO)->values();

        return view('social.amistosos.index', [
            'myClubIds'  => $clubIds,
            'pendientes' => $pendientes,
            'enDisputa'  => $enDisputa,
            'jugados'    => $jugados,
        ]);
    }

    /** El capitán carga el marcador de su equipo (puede rectificar si en disputa). */
    public function report(Request $request, FriendlyMatch $friendlyMatch): RedirectResponse
    {
        $club = $this->participantClubFor($request, $friendlyMatch);

        $data = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'away_score' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        try {
            $match = $this->service->reportResult($friendlyMatch, $club, (int) $data['home_score'], (int) $data['away_score']);
        } catch (FriendlyMatchException $e) {
            return back()->with('error', $e->getMessage());
        }

        $msg = match (true) {
            $match->isJugado()      => 'Resultado confirmado: ambos equipos coinciden.',
            $match->estaEnDisputa() => 'Los marcadores no coinciden. El amistoso quedó en disputa.',
            default                 => 'Marcador cargado. Falta que el otro equipo cargue el suyo.',
        };

        return back()->with('status', $msg);
    }

    /** Escala una disputa a un admin de plataforma. */
    public function escalate(Request $request, FriendlyMatch $friendlyMatch): RedirectResponse
    {
        $club = $this->participantClubFor($request, $friendlyMatch);

        try {
            $this->service->escalate($friendlyMatch, $club);
        } catch (FriendlyMatchException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Disputa escalada. Un administrador la revisará.');
    }

    /** Cancela un amistoso confirmado (penaliza si es dentro de las 24h). */
    public function cancel(Request $request, FriendlyMatch $friendlyMatch): RedirectResponse
    {
        $club = $this->participantClubFor($request, $friendlyMatch);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ], [
            'reason.required' => 'Indicá el motivo de la cancelación.',
        ]);

        try {
            $this->service->cancel($friendlyMatch, $club, $data['reason']);
        } catch (FriendlyMatchException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Amistoso cancelado.');
    }

    /**
     * Resuelve el club participante que el usuario capitanea; aborta 403 si el
     * usuario no es capitán de ninguno de los dos equipos del amistoso.
     */
    private function participantClubFor(Request $request, FriendlyMatch $match): Club
    {
        $user = $request->user();

        $club = Club::whereIn('id', [$match->home_club_id, $match->away_club_id])
            ->where('captain_user_id', $user->id)
            ->first();

        abort_if($club === null, 403, 'Solo el capitán de un equipo participante puede gestionar este amistoso.');

        return $club;
    }
}
