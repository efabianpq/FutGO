<?php

namespace App\Http\Controllers\Admin\Social;

use App\Exceptions\Social\FriendlyMatchException;
use App\Http\Controllers\Controller;
use App\Models\Social\FriendlyMatch;
use App\Services\Social\FriendlyMatchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FutGO Social — Fase 1 · Sesión S1-C · resolución de disputas (admin plataforma).
 *
 * El sistema no fuerza un árbitro externo: cuando los capitanes no concilian, uno
 * de ellos escala y un admin fija el resultado oficial. También expone el
 * historial de cancelaciones (no visible en los perfiles públicos).
 */
class FriendlyMatchController extends Controller
{
    public function __construct(private FriendlyMatchService $service) {}

    /** Bandeja de disputas (escaladas primero) + historial de cancelaciones. */
    public function index(): View
    {
        return view('admin.social.amistosos', [
            'disputes'      => $this->service->disputed(),
            'cancellations' => $this->service->cancellations(),
        ]);
    }

    /** Fija el resultado oficial de un amistoso en disputa. */
    public function resolve(Request $request, FriendlyMatch $friendlyMatch): RedirectResponse
    {
        $data = $request->validate([
            'home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'away_score' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        try {
            $this->service->resolveByAdmin($friendlyMatch, $request->user(), (int) $data['home_score'], (int) $data['away_score']);
        } catch (FriendlyMatchException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Resultado oficial fijado. La disputa quedó resuelta.');
    }
}
