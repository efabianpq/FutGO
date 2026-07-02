<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Services\Privacy\SessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Sesiones y dispositivos activos.
 */
class SessionController extends Controller
{
    public function __construct(private SessionService $sessions)
    {
    }

    public function index(Request $request): View
    {
        return view('privacy.center.sessions', [
            'sessions' => $this->sessions->forUser($request->user(), $request->session()->getId()),
        ]);
    }

    public function destroy(Request $request, string $session): RedirectResponse
    {
        // No permitir cerrar la sesión actual desde acá (para eso está "Salir").
        if ($session !== $request->session()->getId()) {
            $this->sessions->destroy($request->user(), $session);
        }

        return back()->with('status', 'Sesión cerrada.');
    }

    public function destroyOthers(Request $request): RedirectResponse
    {
        $count = $this->sessions->destroyOthers($request->user(), $request->session()->getId());

        return back()->with('status', "Se cerraron {$count} sesión(es) en otros dispositivos.");
    }
}
