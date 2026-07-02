<?php

use Illuminate\Support\Facades\Schedule;

// ─── Módulo Torneos (Sesión G) ───────────────────────────────────────────
// Recordatorios de partidos próximos a jugadores convocados.
Schedule::command('torneos:match-reminders')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/torneos-reminders.log'));

// ─── FutGO Social — Fase 1 (Sesión S1-B) ─────────────────────────────────
// Vence las oportunidades cuya ventana de fecha ya pasó, para que no aparezcan
// en el listado activo. Coexiste con los schedulers de torneos.
Schedule::command('social:expire-opportunities')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/social.log'));

// ─── FutGO Social — Fase 1 (Sesión S1-D) ────────────────────────────────
// Detecta amistosos vencidos sin resultado y registra no_show para ambos clubs.
// Corre cada hora (junto con expire-opportunities).
Schedule::command('social:detect-no-shows')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/social.log'));

// Reconstruye el score de confiabilidad para todos los actores (diario).
Schedule::command('social:rebuild-reliability')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/social.log'));

// ─── Módulo Torneos (deuda #10) ──────────────────────────────────────────
// Reconstruye acumulados, fair play, logros y ranking FUTGO (cache). Hasta
// ahora solo se recalculaba al cerrar un torneo puntual (ReputationService::
// consolidateTournament); este cron lo mantiene fresco aunque no cierre nada.
Schedule::command('torneos:rebuild-reputation')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/torneos-reputation.log'));

// ─── Backups (P-0) ───────────────────────────────────────────────────────
// Solo DB (los medios viven en R2 en producción). Hora 03:00 para minimizar
// conflicto con tráfico. backup:clean corre 30 min después para limpiar viejos.
Schedule::command('backup:run --only-db')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));

Schedule::command('backup:clean')
    ->dailyAt('03:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));
