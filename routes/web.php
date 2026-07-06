<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
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
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Admin\AdminSupportController;
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
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// ─── Consentimiento parental (menores) — confirmación pública firmada ───────
Route::get('/consentimiento-parental/{user}/confirmar', [\App\Http\Controllers\Privacy\GuardianConsentController::class, 'confirm'])
    ->middleware('signed')->name('parental.confirm');

// ─── Baja de comunicaciones comerciales — enlace firmado del pie de email ───
Route::get('/comunicaciones/baja/{user}', \App\Http\Controllers\Privacy\MarketingUnsubscribeController::class)
    ->middleware('signed')->name('comunicaciones.baja');

// ─── Documentos legales (Centro de Privacidad) — públicos, sin auth ─────────
// Requisito Play Store / App Store + Ley 1581/2012. Servidos desde BD
// (versionados), no vistas fijas. Ver App\Http\Controllers\Privacy\LegalController.
Route::controller(\App\Http\Controllers\Privacy\LegalController::class)->group(function () {
    Route::get('/privacidad', 'show')->defaults('type', 'privacy')->name('privacidad');
    Route::get('/terminos',   'show')->defaults('type', 'terms')->name('terminos');
    Route::get('/cookies',    'show')->defaults('type', 'cookies')->name('cookies');
    Route::get('/contenido',  'show')->defaults('type', 'content')->name('contenido');
    Route::get('/menores',    'show')->defaults('type', 'minors')->name('menores');
    // Ruta genérica para el navegador entre documentos (route('legal.show', $type)).
    Route::get('/legal/{type}', 'show')->name('legal.show');
});

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
    Route::get('/buscar', [VenueController::class, 'search'])->middleware('throttle:search')->name('search'); // JSON autocompletado
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

    // ─── Re-aceptación de políticas (Centro de Privacidad) ─────────────────
    // Interceptada por EnsureConsentUpToDate cuando cambia la versión vigente.
    // Consentimiento parental — estado pendiente y reenvío (menor autenticado).
    Route::get('/consentimiento-parental/pendiente', [\App\Http\Controllers\Privacy\GuardianConsentController::class, 'pending'])
        ->name('parental.pending');
    Route::post('/consentimiento-parental/reenviar', [\App\Http\Controllers\Privacy\GuardianConsentController::class, 'resend'])
        ->middleware('throttle:3,10')->name('parental.resend');

    Route::get('/privacidad/aceptar', [\App\Http\Controllers\Privacy\ReconsentController::class, 'show'])
        ->name('privacidad.aceptar');
    Route::post('/privacidad/aceptar', [\App\Http\Controllers\Privacy\ReconsentController::class, 'store'])
        ->name('privacidad.aceptar.store');

    // ─── Centro de Privacidad (habeas data · Ley 1581/2012) ────────────────
    Route::prefix('privacidad/centro')->name('privacidad.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Privacy\CenterController::class, 'index'])->name('centro');

        Route::get('/configuracion', [\App\Http\Controllers\Privacy\SettingsController::class, 'edit'])->name('configuracion');
        Route::patch('/configuracion', [\App\Http\Controllers\Privacy\SettingsController::class, 'update'])->name('configuracion.update');

        Route::get('/consentimientos', [\App\Http\Controllers\Privacy\ConsentController::class, 'index'])->name('consentimientos');
        Route::patch('/consentimientos/marketing', [\App\Http\Controllers\Privacy\ConsentController::class, 'updateMarketing'])->name('consentimientos.marketing');

        Route::get('/sesiones', [\App\Http\Controllers\Privacy\SessionController::class, 'index'])->name('sesiones');
        Route::delete('/sesiones/otras', [\App\Http\Controllers\Privacy\SessionController::class, 'destroyOthers'])->name('sesiones.otras');
        Route::delete('/sesiones/{session}', [\App\Http\Controllers\Privacy\SessionController::class, 'destroy'])->name('sesiones.destroy');

        Route::get('/actividad', [\App\Http\Controllers\Privacy\ActivityController::class, 'index'])->name('actividad');

        // Portabilidad de datos + habeas data
        Route::get('/exportar', [\App\Http\Controllers\Privacy\ExportController::class, 'show'])->name('exportar');
        Route::get('/exportar/descargar', [\App\Http\Controllers\Privacy\ExportController::class, 'download'])
            ->middleware('throttle:6,60')->name('exportar.descargar');
        Route::get('/habeas-data', [\App\Http\Controllers\Privacy\ExportController::class, 'habeasData'])->name('habeas');

        // Derecho al olvido (flujo en pasos con periodo de gracia)
        Route::get('/eliminar', [\App\Http\Controllers\Privacy\AccountDeletionController::class, 'show'])->name('eliminar');
        Route::post('/eliminar/solicitar', [\App\Http\Controllers\Privacy\AccountDeletionController::class, 'request'])
            ->middleware('throttle:3,60')->name('eliminar.solicitar');
        Route::post('/eliminar/verificar', [\App\Http\Controllers\Privacy\AccountDeletionController::class, 'verify'])
            ->middleware('throttle:6,60')->name('eliminar.verificar');
        Route::delete('/eliminar/cancelar', [\App\Http\Controllers\Privacy\AccountDeletionController::class, 'cancel'])->name('eliminar.cancelar');
    });

    Route::group([], function () {
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
            Route::post('/', [OpportunityController::class, 'store'])->middleware('guardian.consent')->name('store');
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

        // ─── Buscador global del header (🔍) — jugadores/clubes/torneos/canchas
        Route::get('/buscar', [\App\Http\Controllers\Social\GlobalSearchController::class, 'index'])
            ->middleware('throttle:search')
            ->name('social.search');

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
            // H20: iniciar DM directo desde perfil de un jugador.
            Route::post('/directo/{user}', [ConversationController::class, 'initiateDirect'])
                ->whereNumber('user')->name('direct');
            // El reporte va ANTES del show numérico para no colisionar con {conversation}.
            Route::post('/mensaje/{message}/reportar', [ConversationController::class, 'reportMessage'])
                ->whereNumber('message')->name('messages.report');
            Route::get('/{conversation}', [ConversationController::class, 'show'])
                ->whereNumber('conversation')->name('show');
            Route::post('/{conversation}', [ConversationController::class, 'store'])
                ->whereNumber('conversation')->name('store');
            Route::post('/{conversation}/compartir-contacto', [ConversationController::class, 'shareContact'])
                ->whereNumber('conversation')->name('share-contact');
            // H20: bloquear al otro participante de una DM directa.
            Route::post('/{conversation}/bloquear', [ConversationController::class, 'block'])
                ->whereNumber('conversation')->name('block');
        });

        // Inicio (v3): dashboard de entrada, igual para todos los usuarios.
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');

        // Compatibilidad: enlaces viejos a /inicio redirigen al nuevo punto de entrada.
        Route::get('/inicio', fn () => redirect()->route('dashboard'))->name('inicio');

        // ─── Módulo Torneos ──────────────────────────────────────────────
        Route::prefix('torneos')
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
                Route::middleware(['throttle:credential-validate'])->group(function () {
                    Route::get('/validar', [CredentialValidationController::class, 'index'])->name('validar');
                    Route::post('/validar', [CredentialValidationController::class, 'validate'])->name('validar.run');
                });

                // ── Equipos PERMANENTES (clubs) — identidad transversal a torneos ──
                Route::post('/equipos', [ClubController::class, 'store'])->middleware('guardian.consent')->name('equipos.store');
                // E6/H9: búsqueda de jugadores por nombre (autocompletado).
                Route::get('/jugadores/buscar', [ClubController::class, 'searchPlayers'])->middleware('throttle:search')->name('jugadores.buscar');
                Route::prefix('clubes/{club:slug}')->name('clubes.')->group(function () {
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
                Route::prefix('{tournament:slug}/mi-equipo')
                    ->name('equipo.')
                    ->group(function () {
                        Route::get('/inscribir', [TeamController::class, 'inscribir'])->name('inscribir');
                        Route::post('/inscribir', [TeamController::class, 'store'])->name('store');
                        Route::get('/', [TeamHubController::class, 'index'])->name('show');
                    });

                // Convocatoria previa (capitán arma + jugadores confirman asistencia).
                Route::prefix('{tournament:slug}/partidos/{match}/convocatoria')
                    ->name('convocatoria.')
                    ->group(function () {
                        Route::get('/', [CallUpController::class, 'manage'])->name('manage');
                        Route::post('/', [CallUpController::class, 'store'])->name('store');
                        Route::post('/responder', [CallUpController::class, 'respond'])->name('respond');
                    });

                // Cronograma público del torneo
                Route::prefix('{tournament:slug}/cronograma')
                    ->name('cronograma.')
                    ->group(function () {
                        Route::get('/', [TournamentScheduleController::class, 'index'])->name('index');
                        Route::get('/equipo/{team:slug}', [TournamentScheduleController::class, 'team'])->name('team');
                    });

                // Estadísticas públicas del torneo
                Route::prefix('{tournament:slug}/estadisticas')
                    ->name('estadisticas.')
                    ->group(function () {
                        Route::get('/', [StatsController::class, 'index'])->name('index');
                        Route::get('/jugador/{teamPlayer}', [StatsController::class, 'jugador'])->name('jugador');
                    });

                // Centro de información del torneo (hub) — solo participantes del torneo
                Route::get('/{tournament:slug}', [TournamentHubController::class, 'index'])
                    ->middleware('ensure.tournament_participant')
                    ->name('hub');
            });

        // ─── Compatibilidad: los portales se unificaron ───────────────────────
        // Mi Actividad → Mi Carrera; Panel Capitán → Mis Equipos.
        Route::name('torneos.')
            ->group(function () {
                Route::get('/mi-actividad', fn () => redirect()->route('torneos.mi-carrera'))->name('mi-actividad');
                Route::get('/capitan', fn () => redirect()->route('torneos.mis-equipos'))->name('capitan');
            });

        // ============ ADMIN TORNEOS ============
        // Cualquier usuario autenticado puede crear torneos; la gestión de un
        // torneo puntual la autoriza cada controlador (creador/tournament_admins
        // o admin maestro), no un gate de rol global.
        Route::prefix('admin/torneos')
            ->name('admin.torneos.')
            ->group(function () {
                // H3: "Gestión Torneos" y "Mis Torneos" se unificaron en una sola
                // vista (torneos.index). Esta ruta redirige para conservar enlaces.
                Route::get('/', fn () => redirect()->route('torneos.index'))->name('index');
                Route::get('/crear', [TournamentController::class, 'create'])->name('create');
                Route::post('/', [TournamentController::class, 'store'])->name('store');
                Route::get('/{tournament:slug}', [TournamentController::class, 'show'])->name('show');
                Route::get('/{tournament:slug}/editar', [TournamentController::class, 'edit'])->name('edit');
                Route::patch('/{tournament:slug}', [TournamentController::class, 'update'])->name('update');
                Route::patch('/{tournament:slug}/estado', [TournamentController::class, 'updateStatus'])->name('status');
                Route::delete('/{tournament:slug}', [TournamentController::class, 'destroy'])->name('destroy');

                // Gestión de equipos (admin del torneo)
                Route::prefix('/{tournament:slug}/equipos')
                    ->name('equipos.')
                    ->group(function () {
                        Route::get('/', [TeamAdminController::class, 'index'])->name('index');
                        // H7: el admin crea equipos (clubs) directamente + asigna capitán.
                        Route::post('/crear', [TeamAdminController::class, 'createClub'])->name('create');
                        Route::get('/{team:slug}', [TeamAdminController::class, 'show'])->name('show');
                        Route::patch('/{team:slug}/asignar-capitan', [TeamAdminController::class, 'assignCaptain'])->name('assignCaptain');
                        Route::patch('/{team:slug}/aprobar', [TeamAdminController::class, 'approve'])->name('approve');
                        Route::patch('/{team:slug}/rechazar', [TeamAdminController::class, 'reject'])->name('reject');
                        // Aprobación de jugadores agregados con el torneo en curso.
                        Route::patch('/{team:slug}/jugadores/{teamPlayer}/aprobar', [TeamAdminController::class, 'approvePlayer'])->name('players.approve');
                        Route::patch('/{team:slug}/jugadores/{teamPlayer}/rechazar', [TeamAdminController::class, 'rejectPlayer'])->name('players.reject');
                        // Bajas y cambios de equipo durante el torneo (con historial).
                        Route::patch('/{team:slug}/jugadores/{teamPlayer}/baja', [TeamAdminController::class, 'releasePlayer'])->name('players.release');
                        Route::patch('/{team:slug}/jugadores/{teamPlayer}/cambio', [TeamAdminController::class, 'transferPlayer'])->name('players.transfer');
                    });

                // Generación de fixture (admin del torneo)
                Route::post('/{tournament:slug}/fixture/generar', [FixtureController::class, 'generate'])
                    ->name('fixture.generate');

                // H6: gestión del fixture en formato liga (admin del torneo).
                Route::prefix('/{tournament:slug}/liga')
                    ->name('liga.')
                    ->group(function () {
                        Route::post('/activar', [MatchSchedulerController::class, 'activate'])->name('activate');
                        Route::post('/partidos', [MatchSchedulerController::class, 'store'])->name('matches.store');
                        Route::post('/round-robin', [MatchSchedulerController::class, 'autoRoundRobin'])->name('roundRobin');
                        Route::delete('/partidos/{match}', [MatchSchedulerController::class, 'destroy'])->name('matches.destroy');
                        Route::post('/eliminatoria', [MatchSchedulerController::class, 'generateKnockout'])->name('knockout');
                    });

                // Cierre de fase y generación de eliminatoria (admin del torneo)
                Route::prefix('/{tournament:slug}/phases')
                    ->name('phases.')
                    ->group(function () {
                        Route::get('/{phase}/close', [PhaseController::class, 'close'])->name('close');
                        Route::post('/{phase}/close', [PhaseController::class, 'doClose'])->name('close.execute');
                    });

                // Tabla de posiciones (admin del torneo)
                Route::prefix('/{tournament:slug}/standings')
                    ->name('standings.')
                    ->group(function () {
                        Route::get('/', [StandingsController::class, 'index'])->name('index');
                        Route::post('/recalculate', [StandingsController::class, 'recalculate'])->name('recalculate');
                    });

                // Patrocinadores del torneo (admin del torneo) — Sesión G
                Route::prefix('/{tournament:slug}/patrocinadores')
                    ->name('sponsors.')
                    ->group(function () {
                        Route::get('/', [SponsorController::class, 'index'])->name('index');
                        Route::post('/', [SponsorController::class, 'store'])->name('store');
                        Route::put('/{sponsor}', [SponsorController::class, 'update'])->name('update');
                        Route::patch('/{sponsor}/toggle', [SponsorController::class, 'toggleActive'])->name('toggle');
                        Route::delete('/{sponsor}', [SponsorController::class, 'destroy'])->name('destroy');
                    });

                // Exportación PDF/CSV del torneo (admin del torneo) — Sesión E
                Route::get('/{tournament:slug}/exportar/{dataset}/{format}', [TournamentExportController::class, 'admin'])
                    ->whereIn('dataset', ['resultados', 'posiciones', 'estadisticas'])
                    ->whereIn('format', ['pdf', 'csv'])
                    ->name('export');

                // Resultados de partidos (admin del torneo)
                Route::prefix('/{tournament:slug}/partidos')
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

            // Usuarios
            Route::get('/usuarios', [AdminUsersController::class, 'index'])->name('users.index');

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

            // Ranking FUTGO — recálculo manual (deuda #10, de plataforma, no por torneo)
            Route::post('/ranking/recalcular', [\App\Http\Controllers\Admin\RankingController::class, 'recalculate'])
                ->name('ranking.recalculate');

            // Centro de Privacidad — versionado de documentos legales
            Route::get('/legal', [\App\Http\Controllers\Admin\Privacy\LegalDocumentController::class, 'index'])->name('legal.index');
            Route::get('/legal/nueva', [\App\Http\Controllers\Admin\Privacy\LegalDocumentController::class, 'create'])->name('legal.create');
            Route::post('/legal', [\App\Http\Controllers\Admin\Privacy\LegalDocumentController::class, 'store'])->name('legal.store');
        });
    });
});

// ═══════════════════════ CENTRO DE SOPORTE ═══════════════════════

// Estado del servicio — SIN auth (para usuarios que no pueden loguearse)
Route::get('/soporte/estado', [SupportController::class, 'status'])->name('soporte.status');

// Satisfacción post-ticket — llega desde un email
Route::get('/soporte/satisfaccion/{ticket}', [SupportController::class, 'satisfaction'])->name('soporte.satisfaction');

// Centro de Soporte — Usuario
Route::middleware('auth')->prefix('soporte')->name('soporte.')->group(function () {
    Route::get('/',                            [SupportController::class, 'index'])->name('index');
    Route::get('/chat',                        [SupportController::class, 'chat'])->name('chat');
    Route::post('/chat',                       [SupportController::class, 'sendMessage'])->middleware('throttle:30,1')->name('chat.send');
    Route::post('/chat/escalar',               [SupportController::class, 'escalate'])->name('chat.escalate');
    Route::get('/ayuda',                       [SupportController::class, 'knowledge'])->name('knowledge');
    Route::get('/ayuda/{article:slug}',        [SupportController::class, 'article'])->name('knowledge.article');
    Route::post('/ayuda/{article}/util',       [SupportController::class, 'markHelpful'])->name('knowledge.helpful');
    Route::get('/mis-casos',                   [SupportController::class, 'myTickets'])->name('my-tickets');
    Route::get('/mis-casos/{ticket}',          [SupportController::class, 'showTicket'])->name('my-tickets.show');
    Route::get('/funcionalidades',             [SupportController::class, 'featureRequests'])->name('features');
    Route::post('/funcionalidades/{fr}/votar', [SupportController::class, 'vote'])->middleware('throttle:5,1')->name('features.vote');
});

// Centro de Soporte — Admin (solo admin global vía middleware `admin` → EnsureAdmin)
Route::middleware(['auth', 'admin'])->prefix('admin/soporte')->name('admin.soporte.')->group(function () {
    Route::get('/',                                   [AdminSupportController::class, 'dashboard'])->name('dashboard');
    Route::get('/tickets',                            [AdminSupportController::class, 'tickets'])->name('tickets');
    Route::get('/tickets/{ticket}',                   [AdminSupportController::class, 'show'])->name('tickets.show');
    Route::patch('/tickets/{ticket}/estado',          [AdminSupportController::class, 'updateStatus'])->name('tickets.status');
    Route::patch('/tickets/{ticket}/asignar',         [AdminSupportController::class, 'assign'])->name('tickets.assign');
    Route::post('/tickets/{ticket}/resolver',         [AdminSupportController::class, 'resolve'])->name('tickets.resolve');
    Route::post('/tickets/{ticket}/generar-articulo', [AdminSupportController::class, 'generateArticle'])->name('tickets.generate-article');
    Route::get('/conocimiento',                       [AdminSupportController::class, 'knowledge'])->name('knowledge');
    Route::post('/conocimiento',                      [AdminSupportController::class, 'storeArticle'])->name('knowledge.store');
    Route::patch('/conocimiento/{article}/publicar',  [AdminSupportController::class, 'publishArticle'])->name('knowledge.publish');
    Route::delete('/conocimiento/{article}',          [AdminSupportController::class, 'deleteArticle'])->name('knowledge.delete');
    Route::get('/estado',                             [AdminSupportController::class, 'statusPanel'])->name('status');
    Route::patch('/estado/{component}',               [AdminSupportController::class, 'updateComponent'])->name('status.update');
    Route::get('/funcionalidades',                    [AdminSupportController::class, 'featureRequests'])->name('features');
    Route::patch('/funcionalidades/{fr}/estado',      [AdminSupportController::class, 'updateFeatureStatus'])->name('features.status');
});

