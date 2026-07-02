<?php

namespace App\Http\Controllers\Privacy;

use App\Http\Controllers\Controller;
use App\Models\Privacy\LegalDocument;
use App\Services\Privacy\ConsentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Centro de Privacidad · Pantalla de re-aceptación de políticas.
 *
 * Se muestra cuando EnsureConsentUpToDate detecta que el usuario aceptó una
 * versión anterior de Términos o Privacidad. Bloquea la navegación hasta aceptar.
 */
class ReconsentController extends Controller
{
    public function __construct(private ConsentService $consents)
    {
    }

    public function show(Request $request): RedirectResponse|View
    {
        $pending = $this->consents->outdatedRequiredTypes($request->user());

        // Nada pendiente → seguir a Inicio (evita quedar atascado en la pantalla).
        if ($pending === []) {
            return redirect()->route('inicio');
        }

        $documents = collect($pending)
            ->map(fn (string $type) => LegalDocument::currentFor($type))
            ->filter();

        return view('privacy.reconsent', [
            'pending'   => $pending,
            'documents' => $documents,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pending = $this->consents->outdatedRequiredTypes($request->user());

        $request->validate([
            'accept' => ['accepted'],
        ], [
            'accept.accepted' => 'Debes aceptar las políticas actualizadas para continuar.',
        ]);

        $this->consents->reconsent($request->user(), $request, $pending);

        \App\Services\Privacy\AuditLogger::record('consent_accepted', $request->user(), null, [
            'types' => $pending, 'source' => 'reconsent',
        ]);

        return redirect()->route('inicio')->with('status', 'Gracias, aceptaste las políticas actualizadas.');
    }
}
