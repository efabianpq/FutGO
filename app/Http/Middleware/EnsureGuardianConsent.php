<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Centro de Privacidad · Limita acciones de menores sin consentimiento parental.
 *
 * Se aplica a las rutas de "operar plenamente" (publicar oportunidades, crear
 * torneos/equipos). Un menor con pending_guardian_consent es redirigido a la
 * pantalla de autorización pendiente. No afecta la navegación de lectura.
 */
class EnsureGuardianConsent
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->pending_guardian_consent) {
            if ($request->expectsJson()) {
                abort(403, 'Tu cuenta espera la autorización de tu representante legal.');
            }

            return redirect()->route('parental.pending')
                ->with('status', 'Necesitas la autorización de tu representante para hacer esto.');
        }

        return $next($request);
    }
}
