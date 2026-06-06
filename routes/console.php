<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('predictions:lock')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('notifications:reminders')
    ->everyMinute()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reminders.log'));

Schedule::command('results:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// ─── Módulo Torneos (Sesión G) ───────────────────────────────────────────
// Recordatorios de partidos próximos a jugadores convocados. Coexiste con los
// schedulers de la polla (de arriba) — no los modifica.
Schedule::command('torneos:match-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/torneos-reminders.log'));
