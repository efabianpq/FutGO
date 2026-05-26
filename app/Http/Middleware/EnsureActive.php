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
            return redirect()->route('activate.show');
        }

        return $next($request);
    }
}
