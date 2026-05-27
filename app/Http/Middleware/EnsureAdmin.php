<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'admin') {
            return redirect()
                ->route('predictions.index')
                ->with('status', 'No tienes permisos para acceder a esta sección.');
        }

        return $next($request);
    }
}
