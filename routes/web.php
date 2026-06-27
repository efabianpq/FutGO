<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FixtureController as AdminFixtureController;
use App\Http\Controllers\Admin\InvitationCodeController as AdminCodesController;
use App\Http\Controllers\Admin\ResultsController as AdminResultsController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\Torneos\FixtureController;
use App\Http\Controllers\Admin\Torneos\MatchSchedulerController;
use App\Http\Controllers\Admin\Torneos\MatchResultController;
use App\Http\Controllers\Admin\Torneos\PhaseController;
use App\Http\Controllers\Admin\Torneos\SponsorController;
use App\Http\Controllers\Admin\Torneos\StandingsController;
use App\Http\Controllers\Admin\Torneos\TeamAdminController;
use App\Http\Controllers\Admin\Torneos\TournamentController;
use App\Http\Controllers\Admin\UserController as AdminUsersController;
use App\Http\Controllers\Torneos\CallUpController;
use App\Http\Controllers\Torneos\ClubController;
use App\Http\Controllers\Torneos\CredentialController;
use App\Http\Controllers\Torneos\CredentialValidationController;
use App\Http\Controllers\Torneos\PublicTournamentController;
use App\Http\Controllers\Torneos\RankingController as TorneosRankingController;
use App\Http\Controllers\Torneos\TournamentExportController;
use App\Http\Controllers\Torneos\TournamentShareController;
use App\Http\Controllers\Torneos\PlayerCareerController;
use App\Http\Controllers\Torneos\MyTeamsController;
use App\Http\Controllers\Torneos\MyTournamentsController;
use App\Http\Controllers\Torneos\ProfileClaimController;
use App\Http\Controllers\Admin\Torneos\ProfileClaimController as AdminProfileClaimController;
use App\Http\Controllers\Torneos\StatsController;
use App\Http\Controllers\Torneos\TeamController;
use App\Http\Controllers\Torneos\TeamHubController;
use App\Http\Controllers\Torneos\TournamentHubController;
use App\Http\Controllers\Torneos\TournamentScheduleController;
use App\Http\Controllers\AuditExportController;
use App\Http\Controllers\Auth\ActivationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PredictionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Social\OpportunityController;
use App\Http\Controllers\Social\FriendlyMatchController;
use App\Http\Controllers\Social\FeedController;
use App\Http\Controllers\Social\FollowController;
use App\Http\Controllers\Social\AgendaController;
use App\Http\Controllers\Social\ConversationController;
use App\Http\Controllers\Admin\Social\FriendlyMatchController as AdminFriendlyMatchController;
use App\Http\Controllers\Admin\Social\ModerationController as AdminModerationController;
use App\Http\Controllers\Social\PlayerPublicController;
use App\Http\Controllers\Social\VenueController;
use App\Http\Controllers\RankingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// Página pública "¿Cómo funciona?" — accesible para guests, auth no-activos y activos
Route::view('/como-funciona', 'como-funciona')->name('how-it-works');

// ─── Portal PÚBLICO del torneo (sin auth) — Sesión E ──────────────────────
// Solo torneos visibility='public' (el controlador hace abort 404 si no lo son).
Route::prefix('t')->name('torneos.public.')->group(function () {
    // H9: portal de exploración de torneos públicos (menú "Torneos").
    Route::get('/', [PublicTournamentController::class, 'index'])->name('index');

    Route::get('/{tournament:slug}', [PublicTournamentController::class, 'show'])->name('show');

    // Exportación pública (PDF/CSV) de datos no sensibles.
    Route::get('/{tournament:slug}/exportar/{dataset}/{format}', [TournamentExportController::class, 'public'])
        ->whereIn('dataset', ['resultados', 'posiciones', 'estadisticas'])
        ->whereIn('format', ['pdf', 'csv'])
        ->name('export');

    // Tarjetas compartibles — SVG (inline) y PNG (descargable). Partido antes del comodín {card}.
    Route::get('/{tournament:slug}/img/partido/{match}', [TournamentShareController::class, 'matchCard'])->whereNumber('match')->name('img.match');
    Route::get('/{tournament:slug}/img/{card}', [TournamentShareController::class, 'card'])
        ->whereIn('card', ['goleadores', 'posiciones', 'mvp'])
        ->name('img');
    // PNG: sirve desde caché o genera en el momento con GD; degrada a SVG si GD no está disponible.
    // Throttle: generación GD es CPU-bound; 20 req/min por IP es suficiente para uso normal.
    Route::middleware('throttle:share-card-png')->group(function () {
        Route::get('/{tournament:slug}/img/partido/{match}/png', [TournamentShareController::class, 'matchCardPng'])->whereNumber('match')->name('img.match.png');
        Route::get('/{tournament:slug}/img/{card}/png', [TournamentShareController::class, 'cardPng'])
            ->whereIn('card', ['goleadores', 'posiciones', 'mvp'])
            ->name('img.png');
    });
});

// ─── FutGO Social · Tarjetas compartibles de amistosos (sin auth) ─────────
// Solo amistosos en estado "jugado" (resultado confirmado) tienen tarjeta.
Route::prefix('amistosos/{friendlyMatch}/img')->name('social.amistosos.img.')->whereNumber('friendlyMatch')->group(function () {
    Route::get('/', [\App\Http\Controllers\Social\FriendlyMatchShareController::class, 'card'])->name('card');
    Route::middleware('throttle:share-card-png')->get('/png', [\App\Http\Controllers\Social\FriendlyMatchShareController::class, 'cardPng'])->name('png');
});

// ─── FutGO Social · Ficha pública de jugador (sin auth) ────────────────────
// Patrón análogo al portal de torneo /t/{slug}. El futgo_id es el identificador
// público del ecosistema (FG-XXXXXX). No expone datos de contacto.
Route::get('/j/{futgo_id}', [PlayerPublicController::class, 'show'])
    ->where('futgo_id', 'FG-[A-Z0-9]{6}')
    ->name('social.player.show');

// ─── FutGO Social · Canchas — listado y perfil PÚBLICO (sin auth) ──────────
Route::prefix('canchas')->name('social.canchas.')->group(function () {
    Route::get('/', [VenueController::class, 'index'])->name('index');
    Route::get('/buscar', [VenueController::class, 'search'])->name('search'); // JSON autocompletado
    Route::get('/{venue:slug}', [VenueController::class, 'show'])->name('show');
});

// ─── FutGO Social · Oportunidades — exploración PÚBLICA (sin auth) ─────────
// Descubrimiento sin login (igual que el portal de torneos). Responder/publicar
// sí requiere cuenta: esas rutas viven bajo `auth` más abajo. {opportunity} se
// restringe a numérico para no capturar /crear ni /mias.
Route::prefix('oportunidades')->name('social.oportunidades.')->group(function () {
    Route::get('/', [OpportunityController::class, 'index'])->name('index');
    Route::get('/{opportunity}', [OpportunityController::class, 'show'])
        ->whereNumber('opportunity')->name('show');
});

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:auth')->name('register.store');

    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:auth')->name('login.store');

    Route::get('/forgot-password', [PasswordResetLinkController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:password-reset')->name('password.email');

    Route::get('/reset-password/{token}', [NewPasswordController::class, 'show'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:password-reset')->name('password.store');
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
        Route::post('/perfil/foto', [ProfileController::class, 'updatePhoto'])->name('profile.photo');

        // ─── Reclamo de perfil (Limitación #2) — jugador + capitán ─────────
        // Fuera del módulo torneos: es el camino de un usuario nuevo para
        // vincularse a un registro 'por_verificar' aunque aún no tenga el módulo.
        Route::prefix('reclamos')->name('torneos.reclamos.')->group(function () {
            Route::get('/', [ProfileClaimController::class, 'index'])->name('index');
            Route::post('/', [ProfileClaimController::class, 'store'])->name('store');
            Route::post('/{claim}/escalar', [ProfileClaimController::class, 'escalate'])
                ->whereNumber('claim')->name('escalate');
            // Bandeja del capitán.
            Route::get('/aprobaciones', [ProfileClaimController::class, 'approvals'])->name('approvals');
            Route::post('/{claim}/aprobar', [ProfileClaimController::class, 'approve'])
                ->whereNumber('claim')->name('approve');
            Route::post('/{claim}/rechazar', [ProfileClaimController::class, 'reject'])
                ->whereNumber('claim')->name('reject');
        });

        // ─── FutGO Social · Oportunidades — acciones (requieren cuenta) ────
        // La exploración pública (index/show) está arriba, fuera de `auth`.
        Route::prefix('oportunidades')->name('social.oportunidades.')->group(function () {
            Route::get('/crear', [OpportunityController::class, 'create'])->name('create');
            // S3-A · Modo rápido: formulario simplificado para rival de último momento.
            Route::get('/rapida', [OpportunityController::class, 'createExpress'])->name('express');
            Route::post('/', [OpportunityController::class, 'store'])->name('store');
            Route::get('/mias', [OpportunityController::class, 'mine'])->name('mine');
            Route::get('/reactivar', [OpportunityController::class, 'reactivar'])->name('reactivar');
            Route::post('/reactivar', [OpportunityController::class, 'confirmarReactivacion'])->name('reactivar.confirmar');

            Route::post('/{opportunity}/responder', [OpportunityController::class, 'respond'])
                ->whereNumber('opportunity')->name('respond');
            Route::post('/{opportunity}/cancelar', [OpportunityController::class, 'cancel'])
                ->whereNumber('opportunity')->name('cancel');
            Route::post('/{opportunity}/reportar', [OpportunityController::class, 'report'])
                ->whereNumber('opportunity')->name('report');

            Route::post('/respuestas/{response}/aceptar', [OpportunityController::class, 'acceptResponse'])
                ->whereNumber('response')->name('responses.accept');
            Route::post('/respuestas/{response}/rechazar', [OpportunityController::class, 'rejectResponse'])
                ->whereNumber('response')->name('responses.reject');
            Route::post('/respuestas/{response}/contrapropuesta', [OpportunityController::class, 'counterResponse'])
                ->whereNumber('response')->name('responses.counter');
        });

        // ─── FutGO Social · Feed de sistema + Seguir — Sesión S1-E ─────────
        Route::get('/feed', [FeedController::class, 'index'])->name('social.feed.index');
        Route::post('/feed/leido', [FeedController::class, 'markRead'])->name('social.feed.read');

        // ─── FutGO Social · Agenda deportiva unificada — Sesión S2-A ───────
        Route::get('/agenda', [AgendaController::class, 'index'])->name('social.agenda.index');

        // Seguir / dejar de seguir (toggle) — {type} = club|user|tournament.
        Route::post('/seguir/{type}/{id}', [FollowController::class, 'toggle'])
            ->whereIn('type', ['club', 'user', 'tournament'])
            ->whereNumber('id')
            ->name('social.follow.toggle');

        // ─── FutGO Social · Amistosos (capitán) — Sesión S1-C ──────────────
        Route::prefix('amistosos')->name('social.amistosos.')->group(function () {
            Route::get('/', [FriendlyMatchController::class, 'index'])->name('index');
            Route::post('/{friendlyMatch}/resultado', [FriendlyMatchController::class, 'report'])
                ->whereNumber('friendlyMatch')->name('report');
            Route::post('/{friendlyMatch}/escalar', [FriendlyMatchController::class, 'escalate'])
                ->whereNumber('friendlyMatch')->name('escalate');
            Route::post('/{friendlyMatch}/cancelar', [FriendlyMatchController::class, 'cancel'])
                ->whereNumber('friendlyMatch')->name('cancel');
        });

        // ─── FutGO Social · Canchas — acciones autenticadas (S3-B) ────────────
        Route::prefix('canchas')->name('social.canchas.')->group(function () {
            Route::get('/nueva', [VenueController::class, 'create'])->name('create');
            Route::post('/', [VenueController::class, 'store'])->name('store');
            Route::get('/{venue:slug}/editar', [VenueController::class, 'edit'])->name('edit');
            Route::patch('/{venue:slug}', [VenueController::class, 'update'])->name('update');
        });

        // ─── FutGO Social · Mensajería en conversaciones existentes — S2-B ──
        Route::prefix('mensajes')->name('social.conversaciones.')->group(function () {
            Route::get('/', [ConversationController::class, 'index'])->name('index');
            // El reporte va ANTES del show numérico para no colisionar con {conversation}.
            Route::post('/mensaje/{message}/reportar', [ConversationController::class, 'reportMessage'])
                ->whereNumber('message')->name('messages.report');
            Route::get('/{conversation}', [ConversationController::class, 'show'])
                ->whereNumber('conversation')->name('show');
            Route::post('/{conversation}', [ConversationController::class, 'store'])
                ->whereNumber('conversation')->name('store');
            Route::post('/{conversation}/compartir-contacto', [ConversationController::class, 'shareContact'])
                ->whereNumber('conversation')->name('share-contact');
        });

        // Auditoría exportable (usuarios activos)
        Route::get('/auditoria/exportar',     [AuditExportController::class, 'index'])->name('audit.index');
        Route::get('/auditoria/exportar/csv', [AuditExportController::class, 'csv'])->name('audit.csv');
        Route::get('/auditoria/exportar/pdf', [AuditExportController::class, 'pdf'])->name('audit.pdf');

        // La página /inicio se retiró (v2.0). El punto de entrada es Mi Carrera
        // para usuarios de torneos; los de polla pura aterrizan en Mis Pronósticos.
        Route::get('/dashboard', function () {
            $user = auth()->user();
            if ($user->hasTorneosAccess()) {
                return redirect()->route('torneos.mi-carrera');
            }
            return redirect()->route('predictions.index');
        })->name('dashboard');

        // Compatibilidad: enlaces viejos a /inicio redirigen al nuevo punto de entrada.
        Route::get('/inicio', fn () => redirect()->route('dashboard'))->name('inicio');

        // ─── Módulo Torneos ──────────────────────────────────────────────
        Route::middleware(['ensure.module:torneos'])
            ->prefix('torneos')
            ->name('torneos.')
            ->group(function () {
                // Entrada principal del módulo: listado de torneos del usuario.
                Route::get('/', [MyTournamentsController::class, 'index'])->name('index');

                // "Mis Equipos" (unificado con el antiguo Panel Capitán): equipos
                // permanentes donde el usuario es capitán o miembro + crear nuevo.
                // Debe ir ANTES de la ruta comodín /{tournament}.
                Route::get('/mis-equipos', [MyTeamsController::class, 'index'])->name('mis-equipos');

                // Hoja de vida deportiva: trayectoria permanente del jugador across torneos.
                Route::get('/mi-carrera', [PlayerCareerController::class, 'show'])->name('mi-carrera');

                // Ranking FUTGO global (Sesión F) — lee cache futgo_rankings.
                Route::get('/ranking', [TorneosRankingController::class, 'index'])->name('ranking');

                // ── Credencial digital antifraude (Sesión D) ──────────────────
                // Credencial propia del jugador (foto, nombre, FUTGO id, QR).
                Route::get('/credencial', [CredentialController::class, 'show'])->name('credencial');

                // Validación por árbitros/admin (escaneo de QR o ingreso manual).
                // Va ANTES del comodín /{tournament} para no ser capturada por él.
                Route::middleware(['ensure.torneo_admin', 'throttle:credential-validate'])->group(function () {
                    Route::get('/validar', [CredentialValidationController::class, 'index'])->name('validar');
                    Route::post('/validar', [CredentialValidationController::class, 'validate'])->name('validar.run');
                });

                // ── Equipos PERMANENTES (clubs) — identidad transversal a torneos ──
                Route::post('/equipos', [ClubController::class, 'store'])->name('equipos.store');
                // E6/H9: búsqueda de jugadores por nombre (autocompletado).
                Route::get('/jugadores/buscar', [ClubController::class, 'searchPlayers'])->name('jugadores.buscar');
                Route::prefix('clubes/{club}')->name('clubes.')->group(function () {
                    Route::get('/', [ClubController::class, 'show'])->name('show');
                    Route::get('/gestionar', [ClubController::class, 'manage'])->name('manage');
                    Route::patch('/', [ClubController::class, 'update'])->name('update');
                    Route::post('/escudo', [ClubController::class, 'updateShield'])->name('shield');
                    Route::post('/jugadores', [ClubController::class, 'addPlayer'])->name('players.add');
                    Route::post('/jugadores-invitado', [ClubController::class, 'addGuestPlayer'])->name('players.addGuest');
                    Route::delete('/jugadores/{clubPlayer}', [ClubController::class, 'removePlayer'])->name('players.remove');
                    Route::patch('/capitan', [ClubController::class, 'setCaptain'])->name('captain');
                    // S3-A: ignorar la sugerencia de recategorización de nivel.
                    Route::post('/sugerencia-nivel/ignorar', [ClubController::class, 'dismissLevelSuggestion'])
                        ->name('level-suggestion.dismiss');
                });

                // Inscripción a un torneo = enrolar un equipo permamente existente.
                Route::prefix('{tournament}/mi-equipo')
                    ->name('equipo.')
                    ->group(function () {
                        Route::get('/inscribir', [TeamController::class, 'inscribir'])->name('inscribir');
                        Route::post('/inscribir', [TeamController::class, 'store'])->name('store');
                        Route::get('/', [TeamHubController::class, 'index'])->name('show');
                    });

                // Convocatoria previa (capitán arma + jugadores confirman asistencia).
                Route::prefix('{tournament}/partidos/{match}/convocatoria')
                    ->name('convocatoria.')
                    ->group(function () {
                        Route::get('/', [CallUpController::class, 'manage'])->name('manage');
                        Route::post('/', [CallUpController::class, 'store'])->name('store');
                        Route::post('/responder', [CallUpController::class, 'respond'])->name('respond');
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

        // ─── Compatibilidad: los portales se unificaron ───────────────────────
        // Mi Actividad → Mi Carrera; Panel Capitán → Mis Equipos.
        Route::middleware(['ensure.module:torneos'])
            ->name('torneos.')
            ->group(function () {
                Route::get('/mi-actividad', fn () => redirect()->route('torneos.mi-carrera'))->name('mi-actividad');
                Route::get('/capitan', fn () => redirect()->route('torneos.mis-equipos'))->name('capitan');
            });

        // ============ ADMIN TORNEOS ============
        Route::middleware(['ensure.torneo_admin'])
            ->prefix('admin/torneos')
            ->name('admin.torneos.')
            ->group(function () {
                // H3: "Gestión Torneos" y "Mis Torneos" se unificaron en una sola
                // vista (torneos.index). Esta ruta redirige para conservar enlaces.
                Route::get('/', fn () => redirect()->route('torneos.index'))->name('index');
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
                        // H7: el admin crea equipos (clubs) directamente + asigna capitán.
                        Route::post('/crear', [TeamAdminController::class, 'createClub'])->name('create');
                        Route::get('/{team}', [TeamAdminController::class, 'show'])->name('show');
                        Route::patch('/{team}/asignar-capitan', [TeamAdminController::class, 'assignCaptain'])->name('assignCaptain');
                        Route::patch('/{team}/aprobar', [TeamAdminController::class, 'approve'])->name('approve');
                        Route::patch('/{team}/rechazar', [TeamAdminController::class, 'reject'])->name('reject');
                        // Aprobación de jugadores agregados con el torneo en curso.
                        Route::patch('/{team}/jugadores/{teamPlayer}/aprobar', [TeamAdminController::class, 'approvePlayer'])->name('players.approve');
                        Route::patch('/{team}/jugadores/{teamPlayer}/rechazar', [TeamAdminController::class, 'rejectPlayer'])->name('players.reject');
                        // Bajas y cambios de equipo durante el torneo (con historial).
                        Route::patch('/{team}/jugadores/{teamPlayer}/baja', [TeamAdminController::class, 'releasePlayer'])->name('players.release');
                        Route::patch('/{team}/jugadores/{teamPlayer}/cambio', [TeamAdminController::class, 'transferPlayer'])->name('players.transfer');
                    });

                // Generación de fixture (torneo_admin)
                Route::post('/{tournament}/fixture/generar', [FixtureController::class, 'generate'])
                    ->name('fixture.generate');

                // H6: gestión del fixture en formato liga (torneo_admin).
                Route::prefix('/{tournament}/liga')
                    ->name('liga.')
                    ->group(function () {
                        Route::post('/activar', [MatchSchedulerController::class, 'activate'])->name('activate');
                        Route::post('/partidos', [MatchSchedulerController::class, 'store'])->name('matches.store');
                        Route::post('/round-robin', [MatchSchedulerController::class, 'autoRoundRobin'])->name('roundRobin');
                        Route::delete('/partidos/{match}', [MatchSchedulerController::class, 'destroy'])->name('matches.destroy');
                        Route::post('/eliminatoria', [MatchSchedulerController::class, 'generateKnockout'])->name('knockout');
                    });

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

                // Patrocinadores del torneo (torneo_admin) — Sesión G
                Route::prefix('/{tournament}/patrocinadores')
                    ->name('sponsors.')
                    ->group(function () {
                        Route::get('/', [SponsorController::class, 'index'])->name('index');
                        Route::post('/', [SponsorController::class, 'store'])->name('store');
                        Route::delete('/{sponsor}', [SponsorController::class, 'destroy'])->name('destroy');
                    });

                // Exportación PDF/CSV del torneo (torneo_admin) — Sesión E
                Route::get('/{tournament}/exportar/{dataset}/{format}', [TournamentExportController::class, 'admin'])
                    ->whereIn('dataset', ['resultados', 'posiciones', 'estadisticas'])
                    ->whereIn('format', ['pdf', 'csv'])
                    ->name('export');

                // Resultados de partidos (torneo_admin)
                Route::prefix('/{tournament}/partidos')
                    ->name('partidos.')
                    ->group(function () {
                        Route::get('/', [MatchResultController::class, 'index'])->name('index');
                        Route::get('/{match}/programar', [MatchResultController::class, 'editSchedule'])->name('programar');
                        Route::patch('/{match}/programar', [MatchResultController::class, 'updateSchedule'])->name('programar.update');
                        Route::get('/{match}/resultado', [MatchResultController::class, 'show'])->name('resultado');
                        Route::get('/{match}/pdf', [MatchResultController::class, 'pdf'])->name('pdf');
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

            // FutGO Social — disputas y cancelaciones de amistosos (Sesión S1-C)
            Route::get('/amistosos', [AdminFriendlyMatchController::class, 'index'])->name('amistosos.index');
            Route::post('/amistosos/{friendlyMatch}/resolver', [AdminFriendlyMatchController::class, 'resolve'])
                ->whereNumber('friendlyMatch')->name('amistosos.resolve');

            // FutGO Social — Moderación de contenido (Sesión S1-F)
            Route::get('/moderacion', [AdminModerationController::class, 'index'])->name('social.moderacion.index');
            Route::post('/moderacion/{report}/resolver', [AdminModerationController::class, 'resolve'])
                ->whereNumber('report')->name('social.moderacion.resolve');

            // Reclamos de perfil ESCALADOS (Limitación #2) — sin capitán activo.
            Route::get('/torneos/reclamos', [AdminProfileClaimController::class, 'index'])->name('torneos.reclamos.index');
            Route::post('/torneos/reclamos/{claim}/aprobar', [AdminProfileClaimController::class, 'approve'])
                ->whereNumber('claim')->name('torneos.reclamos.approve');
            Route::post('/torneos/reclamos/{claim}/rechazar', [AdminProfileClaimController::class, 'reject'])
                ->whereNumber('claim')->name('torneos.reclamos.reject');

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
