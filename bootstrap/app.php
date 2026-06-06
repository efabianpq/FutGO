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
            'ensure.active' => \App\Http\Middleware\EnsureActive::class,
            'redirect.if.active' => \App\Http\Middleware\RedirectIfActive::class,
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'ensure.module'        => \App\Http\Middleware\EnsureModule::class,
            'ensure.torneo_admin'  => \App\Http\Middleware\EnsureTorneoAdmin::class,
            'ensure.tournament_participant' => \App\Http\Middleware\EnsureTournamentParticipant::class,
        ]);

        $middleware->redirectGuestsTo('/login');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
