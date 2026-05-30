<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FixtureController as AdminFixtureController;
use App\Http\Controllers\Admin\InvitationCodeController as AdminCodesController;
use App\Http\Controllers\Admin\ResultsController as AdminResultsController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\Torneos\FixtureController;
use App\Http\Controllers\Admin\Torneos\MatchResultController;
use App\Http\Controllers\Admin\Torneos\PhaseController;
use App\Http\Controllers\Admin\Torneos\StandingsController;
use App\Http\Controllers\Admin\Torneos\TeamAdminController;
use App\Http\Controllers\Admin\Torneos\TournamentController;
use App\Http\Controllers\Admin\UserController as AdminUsersController;
use App\Http\Controllers\Torneos\StatsController;
use App\Http\Controllers\Torneos\TeamController;
use App\Http\Controllers\Torneos\TeamHubController;
use App\Http\Controllers\Torneos\TournamentHubController;
use App\Http\Controllers\Torneos\TournamentScheduleController;
use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PredictionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Página pública "¿Cómo funciona?" — accesible para guests, auth no-activos y activos
Route::view('/como-funciona', 'como-funciona')->name('how-it-works');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::middleware('redirect.if.active')->group(function () {
        Route::get('/activate', [ActivationController::class, 'show'])->name('activate.show');
        Route::post('/activate', [ActivationController::class, 'store'])->name('activate.store');
    });

    Route::middleware('ensure.active')->group(function () {
        // Pronósticos
        Route::get('/predictions', [PredictionsController::class, 'index'])->name('predictions.index');
        Route::get('/predictions/states', [PredictionsController::class, 'states'])->name('predictions.states');
        Route::post('/predictions/{game}', [PredictionsController::class, 'update'])->name('predictions.update');
        Route::get('/partidos/{game}/pronosticos', [PredictionsController::class, 'matchPredictions'])->name('predictions.byMatch');

        // Ranking (solo participantes activos)
        Route::get('/ranking', [RankingController::class, 'index'])->name('ranking.index');
        Route::get('/ranking/data', [RankingController::class, 'data'])->name('ranking.data');
        Route::get('/ranking/u/{user}', [RankingController::class, 'show'])->name('ranking.show');

        Route::get('/perfil', [ProfileController::class, 'show'])->name('profile.show');
        Route::patch('/perfil', [ProfileController::class, 'update'])->name('profile.update');

        // Auditoría exportable (usuarios activos)
        Route::get('/auditoria/exportar',     [AuditExportController::class, 'index'])->name('audit.index');
        Route::get('/auditoria/exportar/csv', [AuditExportController::class, 'csv'])->name('audit.csv');
        Route::get('/auditoria/exportar/pdf', [AuditExportController::class, 'pdf'])->name('audit.pdf');

        // Dashboard principal modular (tarjetas según módulos del usuario)
        Route::get('/inicio', [DashboardController::class, 'index'])->name('inicio');

        // Alias compatible: los usuarios exclusivos de torneos aterrizan en el
        // dashboard modular; el resto mantiene la compatibilidad con polla.
        Route::get('/dashboard', function () {
            $user = auth()->user();
            if ($user->hasTorneosAccess() && ! $user->hasPollaAccess()) {
                return redirect()->route('inicio');
            }
            return redirect()->route('predictions.index');
        })->name('dashboard');

        // ─── Módulo Torneos ──────────────────────────────────────────────
        Route::middleware(['ensure.module:torneos'])
            ->prefix('torneos')
            ->name('torneos.')
            ->group(function () {
                Route::get('/', function () {
                    return view('torneos.index');
                })->name('index');

                // Gestión de equipo (capitán / jugadores) — Centro de Gestión de Equipos
                Route::prefix('{tournament}/mi-equipo')
                    ->name('equipo.')
                    ->group(function () {
                        Route::get('/inscribir', [TeamController::class, 'inscribir'])->name('inscribir');
                        Route::post('/inscribir', [TeamController::class, 'store'])->name('store');
                        Route::get('/', [TeamHubController::class, 'index'])->name('show');
                        Route::post('/jugadores', [TeamController::class, 'addPlayer'])->name('players.add');
                        Route::delete('/jugadores/{teamPlayer}', [TeamController::class, 'removePlayer'])->name('players.remove');

                        // Aprobación/rechazo de solicitudes (capitán/torneo_admin) — protegido por ensure.team_member
                        Route::post('/players/{player}/approve', [TeamHubController::class, 'approvePlayer'])
                            ->middleware('ensure.team_member')->name('players.approve');
                        Route::post('/players/{player}/reject', [TeamHubController::class, 'rejectPlayer'])
                            ->middleware('ensure.team_member')->name('players.reject');
                    });

                // Cronograma público del torneo
                Route::prefix('{tournament}/cronograma')
                    ->name('cronograma.')
                    ->group(function () {
                        Route::get('/', [TournamentScheduleController::class, 'index'])->name('index');
                        Route::get('/equipo/{team}', [TournamentScheduleController::class, 'team'])->name('team');
                    });

                // Estadísticas públicas del torneo
                Route::prefix('{tournament}/estadisticas')
                    ->name('estadisticas.')
                    ->group(function () {
                        Route::get('/', [StatsController::class, 'index'])->name('index');
                        Route::get('/jugador/{teamPlayer}', [StatsController::class, 'jugador'])->name('jugador');
                    });

                // Centro de información del torneo (hub) — solo participantes del torneo
                Route::get('/{tournament}', [TournamentHubController::class, 'index'])
                    ->middleware('ensure.tournament_participant')
                    ->name('hub');
            });

        // ============ ADMIN TORNEOS ============
        Route::middleware(['ensure.torneo_admin'])
            ->prefix('admin/torneos')
            ->name('admin.torneos.')
            ->group(function () {
                Route::get('/', [TournamentController::class, 'index'])->name('index');
                Route::get('/crear', [TournamentController::class, 'create'])->name('create');
                Route::post('/', [TournamentController::class, 'store'])->name('store');
                Route::get('/{tournament}', [TournamentController::class, 'show'])->name('show');
                Route::get('/{tournament}/editar', [TournamentController::class, 'edit'])->name('edit');
                Route::patch('/{tournament}', [TournamentController::class, 'update'])->name('update');
                Route::patch('/{tournament}/estado', [TournamentController::class, 'updateStatus'])->name('status');
                Route::delete('/{tournament}', [TournamentController::class, 'destroy'])->name('destroy');

                // Gestión de equipos (torneo_admin)
                Route::prefix('/{tournament}/equipos')
                    ->name('equipos.')
                    ->group(function () {
                        Route::get('/', [TeamAdminController::class, 'index'])->name('index');
                        Route::get('/{team}', [TeamAdminController::class, 'show'])->name('show');
                        Route::patch('/{team}/aprobar', [TeamAdminController::class, 'approve'])->name('approve');
                        Route::patch('/{team}/rechazar', [TeamAdminController::class, 'reject'])->name('reject');
                    });

                // Generación de fixture (torneo_admin)
                Route::post('/{tournament}/fixture/generar', [FixtureController::class, 'generate'])
                    ->name('fixture.generate');

                // Cierre de fase y generación de eliminatoria (torneo_admin)
                Route::prefix('/{tournament}/phases')
                    ->name('phases.')
                    ->group(function () {
                        Route::get('/{phase}/close', [PhaseController::class, 'close'])->name('close');
                        Route::post('/{phase}/close', [PhaseController::class, 'doClose'])->name('close.execute');
                    });

                // Tabla de posiciones (torneo_admin)
                Route::prefix('/{tournament}/standings')
                    ->name('standings.')
                    ->group(function () {
                        Route::get('/', [StandingsController::class, 'index'])->name('index');
                        Route::post('/recalculate', [StandingsController::class, 'recalculate'])->name('recalculate');
                    });

                // Resultados de partidos (torneo_admin)
                Route::prefix('/{tournament}/partidos')
                    ->name('partidos.')
                    ->group(function () {
                        Route::get('/', [MatchResultController::class, 'index'])->name('index');
                        Route::get('/{match}/resultado', [MatchResultController::class, 'show'])->name('resultado');
                        Route::post('/{match}/resultado', [MatchResultController::class, 'store'])->name('store');
                        Route::patch('/{match}/en-vivo', [MatchResultController::class, 'markLive'])->name('live');
                        Route::delete('/{match}/resultado', [MatchResultController::class, 'destroy'])->name('destroy');
                    });
            });

        Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
            Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

            // Códigos
            Route::get('/codigos', [AdminCodesController::class, 'index'])->name('codes.index');
            Route::post('/codigos/generar', [AdminCodesController::class, 'generate'])->name('codes.generate');
            Route::patch('/codigos/{code}/desactivar', [AdminCodesController::class, 'deactivate'])->name('codes.deactivate');
            Route::get('/codigos/exportar', [AdminCodesController::class, 'export'])->name('codes.export');

            // Usuarios
            Route::get('/usuarios', [AdminUsersController::class, 'index'])->name('users.index');
            Route::patch('/usuarios/{user}/toggle', [AdminUsersController::class, 'toggleActive'])->name('users.toggle');

            // Fixture
            Route::get('/fixture', [AdminFixtureController::class, 'index'])->name('fixture.index');
            Route::get('/fixture/{game}/editar', [AdminFixtureController::class, 'edit'])->name('fixture.edit');
            Route::patch('/fixture/{game}', [AdminFixtureController::class, 'update'])->name('fixture.update');

            // Resultados
            Route::get('/resultados', [AdminResultsController::class, 'index'])->name('results.index');
            Route::post('/resultados/sync', [AdminResultsController::class, 'syncNow'])->name('results.sync');
            Route::post('/resultados/{game}', [AdminResultsController::class, 'store'])->name('results.store');

            // Configuración
            Route::get('/configuracion', [AdminSettingsController::class, 'edit'])->name('settings.edit');
            Route::post('/configuracion', [AdminSettingsController::class, 'update'])->name('settings.update');

            // Auditoría exportable (admin)
            Route::get('/auditoria/exportar',     [AuditExportController::class, 'index'])->name('audit.index');
            Route::get('/auditoria/exportar/csv', [AuditExportController::class, 'csv'])->name('audit.csv');
            Route::get('/auditoria/exportar/pdf', [AuditExportController::class, 'pdf'])->name('audit.pdf');
        });
    });
});
