<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->is_active) {
            $route = $request->route()?->getName() ?? '';

            $msg = match (true) {
                str_starts_with($route, 'ranking.') => 'Activá tu código de invitación para acceder al ranking.',
                default => 'Activá tu código de invitación para continuar.',
            };

            return redirect()->route('activate.show')->with('status', $msg);
        }

        return $next($request);
    }
}
