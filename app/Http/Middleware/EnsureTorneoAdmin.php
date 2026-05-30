<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTorneoAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->isTorneoAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Acceso no autorizado'], 403);
            }
            return redirect()->route('predictions.index')
                ->with('error', 'No tenés permisos para administrar torneos.');
        }
        return $next($request);
    }
}
