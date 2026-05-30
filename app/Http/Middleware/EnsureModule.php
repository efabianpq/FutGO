<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (!$request->user()?->hasModuleAccess($module)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Acceso no autorizado'], 403);
            }
            return redirect()->route('predictions.index')
                ->with('error', 'No tenés acceso a esta sección.');
        }
        return $next($request);
    }
}
