<?php

namespace App\Providers;

use App\Services\Privacy\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Morph map estable (FutGO Social): los tipos polimórficos guardan alias
        // cortos ('user', 'club', 'tournament', ...) en vez del FQCN, de modo que
        // renombrar/mover una clase no invalida las filas históricas.
        Relation::morphMap([
            'user'            => \App\Models\User::class,
            'club'            => \App\Models\Torneos\Club::class,
            'tournament'      => \App\Models\Torneos\Tournament::class,
            'opportunity'     => \App\Models\Social\Opportunity::class,
            'friendly_match'  => \App\Models\Social\FriendlyMatch::class,
            'message'         => \App\Models\Social\Message::class,
            'achievement'     => \App\Models\Torneos\Achievement::class,
            'feed_event'      => \App\Models\Social\FeedEvent::class,
            'venue'           => \App\Models\Social\Venue::class,
            // Centro de Privacidad
            'legal_document'  => \App\Models\Privacy\LegalDocument::class,
            'user_consent'    => \App\Models\Privacy\UserConsent::class,
            'audit_log'       => \App\Models\Privacy\AuditLog::class,
            'data_request'    => \App\Models\Privacy\DataRequest::class,
        ]);

        // Contadores de no leídos compartidos con el navbar (componente nav):
        // Feed de sistema (S1-E) y mensajería en conversaciones (S2-B).
        \Illuminate\Support\Facades\View::composer('components.nav', function ($view) {
            $user = $view->user ?? auth()->user();
            $view->with('feedUnreadCount', $user
                ? app(\App\Services\Social\FeedService::class)->unreadCount($user)
                : 0);
            $view->with('messagesUnreadCount', $user
                ? app(\App\Services\Social\ConversationService::class)->unreadCount($user)
                : 0);

            // Reclamos de perfil pendientes de aprobación en los clubs que capitanea
            // el usuario (Limitación #2) — alimenta el badge "Reclamos" del navbar.
            $view->with('pendingClaimApprovals', $user
                ? \App\Models\Torneos\ProfileClaim::pending()
                    ->whereIn('club_id', \App\Models\Torneos\Club::where('captain_user_id', $user->id)->select('id'))
                    ->count()
                : 0);
        });

        // 5 intentos/min por IP — login y registro
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // 3 intentos/min por IP — recuperación y reset de contraseña
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // 30 validaciones/min por usuario autenticado (o IP como fallback)
        RateLimiter::for('credential-validate', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // 20 tarjetas PNG/min por IP — GD es CPU-bound; cache mitiga la mayoría de reqs.
        RateLimiter::for('share-card-png', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // 100 búsquedas/min por IP — buscador global, autocompletado de canchas
        // y de jugadores por club. Endpoints de lectura pero costosos si se abusan.
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        // Auditoría de eventos de autenticación (Centro de Privacidad).
        Event::listen(Login::class, fn (Login $e) => AuditLogger::record('login', $e->user));
        Event::listen(Logout::class, fn (Logout $e) => AuditLogger::record('logout', $e->user));
        Event::listen(PasswordReset::class, fn (PasswordReset $e) => AuditLogger::record('password_changed', $e->user));
    }
}
