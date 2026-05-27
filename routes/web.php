<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FixtureController as AdminFixtureController;
use App\Http\Controllers\Admin\InvitationCodeController as AdminCodesController;
use App\Http\Controllers\Admin\ResultsController as AdminResultsController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\UserController as AdminUsersController;
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

        // Alias compatible
        Route::get('/dashboard', fn () => redirect()->route('predictions.index'))->name('dashboard');

        // ============ ADMIN ============
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
            Route::post('/resultados/{game}', [AdminResultsController::class, 'store'])->name('results.store');

            // Configuración
            Route::get('/configuracion', [AdminSettingsController::class, 'edit'])->name('settings.edit');
            Route::post('/configuracion', [AdminSettingsController::class, 'update'])->name('settings.update');
        });
    });
});
