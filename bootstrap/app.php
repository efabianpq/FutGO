<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'ensure.tournament_participant' => \App\Http\Middleware\EnsureTournamentParticipant::class,
            'guardian.consent' => \App\Http\Middleware\EnsureGuardianConsent::class,
        ]);

        $middleware->redirectGuestsTo('/login');

        // CORS para el wrapper Capacitor (WebView): HandleCors ya corre en el
        // stack global de Laravel 11 por defecto; ver config/cors.php para
        // los orígenes permitidos (allowed_origins/supports_credentials).
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Headers de seguridad HTTP (HSTS, X-Frame-Options, etc.) en toda
        // respuesta web — ver app/Http/Middleware/SecurityHeaders.php.
        // EnsureConsentUpToDate fuerza re-aceptar políticas si cambió su versión.
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureConsentUpToDate::class,
        ]);

        $middleware->validateCsrfTokens(except: []);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
