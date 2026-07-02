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

// ─── Eliminación de cuenta (cumplimiento Play Store / App Store) ────────────
// Reporta cuentas anonimizadas hace 30+ días para revisión manual (no borra
// filas — ver comentario en PurgeDeletedAccounts sobre FKs cascadeOnDelete).
Schedule::command('futgo:purge-deleted-accounts')
    ->dailyAt('04:30')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/purge-accounts.log'));

// ─── Centro de Soporte ──────────────────────────────────────────────────────
// Monitor de estado de componentes (cada 5 minutos). Actualiza
// support_service_status y crea tickets internos si algún componente cae.
Schedule::call(function () {
    app(\App\Services\Support\StatusMonitorService::class)->runAllChecks();
})
    ->name('support-monitor')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/support-monitor.log'));

// Envía emails de satisfacción a tickets resueltos pendientes (cada hora).
Schedule::command('support:send-satisfaction-emails')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/support-satisfaction.log'));
