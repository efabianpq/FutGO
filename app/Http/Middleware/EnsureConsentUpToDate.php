<?php

namespace App\Http\Middleware;

use App\Services\Privacy\ConsentService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centro de Privacidad · Fuerza re-aceptación de documentos obligatorios.
 *
 * Si se publicó una versión nueva de Términos o Privacidad desde la última
 * aceptación del usuario, lo redirige a la pantalla de re-consentimiento y
 * bloquea el resto de la navegación hasta que acepte.
 *
 * Whitelist: la propia pantalla de aceptación, el logout, los documentos legales
 * públicos y peticiones no-GET de esas rutas. Nunca afecta a invitados.
 */
class EnsureConsentUpToDate
{
    /** Nombres de ruta que no deben ser interceptados (evita loops). */
    private const ALLOWED_ROUTES = [
        'privacidad.aceptar',
        'privacidad.aceptar.store',
        'logout',
        'privacidad', 'terminos', 'cookies', 'contenido', 'menores', 'legal.show',
    ];

    public function __construct(private ConsentService $consents)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        // No interceptar peticiones AJAX/JSON (autocompletados, toggles): responden
        // 409 para que el front pueda avisar sin romper la UX.
        if ($this->consents->outdatedRequiredTypes($user) !== []) {
            if ($request->expectsJson()) {
                abort(409, 'Debes aceptar la versión actualizada de nuestras políticas.');
            }

            return redirect()->route('privacidad.aceptar');
        }

        return $next($request);
    }
}
