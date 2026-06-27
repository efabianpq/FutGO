<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Services\Social\FeedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * FutGO Social — Fase 1 · Sesión S1-E · Feed de eventos del sistema.
 *
 * Muestra los eventos relevantes (paginados). Abrir el Feed marca todo como
 * leído (el contador del navbar vuelve a cero). Si todavía no hay nada relevante
 * —usuario nuevo sin follows— se ofrecen oportunidades activas de su ciudad como
 * contenido de entrada.
 */
class FeedController extends Controller
{
    public function __construct(private FeedService $service) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $events = $this->service->feedFor($user);

        // Contenido de entrada cuando el Feed está vacío (primer ingreso).
        $entryOpportunities = $events->isEmpty()
            ? $this->service->entryOpportunities($user)
            : collect();

        // Abrir el Feed = leer: el badge del navbar baja a cero. Solo en la 1ª página.
        if ($request->integer('page', 1) === 1) {
            $this->service->markRead($user);
        }

        return view('social.feed.index', [
            'events'             => $events,
            'entryOpportunities' => $entryOpportunities,
            'hasCity'            => (bool) $user->city,
        ]);
    }

    /** Marca todo como leído sin recargar (botón explícito "marcar como leído"). */
    public function markRead(Request $request): RedirectResponse
    {
        $this->service->markRead($request->user());

        return back()->with('status', 'Marcamos todo como leído.');
    }
}
