<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Social\Conversation;
use App\Models\Social\Opportunity;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\PlayerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\User;
use App\Models\Torneos\Club;
use App\Services\Social\FriendlyMatchService;
use App\Services\Social\PlayedWithService;
use Illuminate\View\View;

/**
 * FutGO Social — S1-F · Ficha pública de jugador.
 *
 * Accesible sin autenticación (análogo al portal /t/{slug}). El futgo_id es el
 * identificador público del ecosistema y NO expone ningún dato de contacto.
 * Select explícito de columnas para garantizar que email, teléfono y documento
 * nunca llegan a la vista.
 */
class PlayerPublicController extends Controller
{
    /** Score mínimo para mostrar el score de confiabilidad en el perfil público. */
    public const MIN_SCORE_VISIBLE = 80;

    public function __construct(
        private FriendlyMatchService $friendlyMatches,
        private PlayedWithService $playedWith,
    ) {}

    public function show(string $futgo_id): View
    {
        // Columnas explícitas: NUNCA incluir email, phone_whatsapp ni document.
        $player = User::where('futgo_id', $futgo_id)
            ->select([
                'id', 'name', 'futgo_id', 'avatar_url',
                'play_level', 'city',
                'is_suspended', 'suspended_until',
                'accepts_direct_messages',
            ])
            ->firstOrFail();

        // Configuración de privacidad del jugador (sin persistir en lectura).
        $privacy = $player->privacySetting;
        $authUser = auth()->user();
        $isOwnerOrAdmin = $authUser && ($authUser->id === $player->id || $authUser->isAdmin());

        // Perfil no público: 404 salvo para el dueño o un admin.
        abort_if($privacy && ! $privacy->public_profile && ! $isOwnerOrAdmin, 404);

        // Toggles de visibilidad: si están apagados, no se pasa el dato a la vista.
        if ($privacy && ! $privacy->show_city && ! $isOwnerOrAdmin) {
            $player->city = null;
        }

        // Perfil suspendido: aún visible para no dar info de si existe, pero con
        // señal discreta (sin datos sensibles adicionales).
        $isSuspended = $player->isSuspended();

        // Career stats (acumulado histórico) — respeta show_stats.
        $showStats = $isOwnerOrAdmin || ! $privacy || $privacy->show_stats;
        $career = $showStats ? PlayerCareerStat::where('user_id', $player->id)->first() : null;

        // Logros desbloqueados (ordenados por fecha de obtención).
        $achievements = $player->achievements()
            ->orderByPivot('awarded_at', 'desc')
            ->get(['achievements.id', 'name', 'description', 'icon', 'user_achievements.awarded_at']);

        // Historial de temporadas (torneos en los que participó con stats).
        // Respeta show_history.
        $showHistory = $isOwnerOrAdmin || ! $privacy || $privacy->show_history;
        $seasons = $showHistory ? TeamPlayer::where('user_id', $player->id)
            ->with([
                'team:id,tournament_id,club_id',
                'team.tournament:id,name,slug,status',
                'team.club:id,name,slug,shield_url',
                'playerStat',
            ])
            ->get()
            ->filter(fn ($tp) => $tp->team?->tournament !== null)
            ->sortByDesc(fn ($tp) => $tp->team->tournament->created_at)
            ->values() : collect();

        // Oportunidades BUSCAR_EQUIPO abiertas: este jugador busca equipo.
        $openOpportunities = Opportunity::query()
            ->where('user_id', $player->id)
            ->where('type', Opportunity::TYPE_BUSCAR_EQUIPO)
            ->active()
            ->visible()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        // Métricas sociales de amistosos.
        $friendlyMetrics = $this->friendlyMatches->userMetrics($player);

        // Score de confiabilidad: solo visible en el perfil público si supera el umbral.
        $reliabilityScore = null;
        $score = ReliabilityScore::where('subject_type', 'user')
            ->where('subject_id', $player->id)
            ->first();
        if ($score && $score->score >= self::MIN_SCORE_VISIBLE) {
            $reliabilityScore = $score;
        }

        // Cantidad de seguidores del jugador.
        $followersCount = $player->followers()->count();

        // Botón de seguir: solo si hay sesión iniciada y no es el mismo usuario.
        $isFollowing = false;
        $authUser = auth()->user();
        if ($authUser && $authUser->id !== $player->id) {
            $isFollowing = $authUser->follows()
                ->where('followable_type', 'user')
                ->where('followable_id', $player->id)
                ->exists();
        }

        // S2-A "Jugué con vos": conteo derivado de partidos compartidos entre el
        // visitante autenticado y este jugador. Habilita las acciones directas
        // (retar / invitar). El visitante puede actuar como capitán si lo es.
        $sharedCount   = 0;
        $viewerIsCaptain = false;
        if ($authUser && $authUser->id !== $player->id) {
            $sharedCount     = $this->playedWith->sharedCount($authUser, $player);
            $viewerIsCaptain = Club::where('captain_user_id', $authUser->id)->exists();
        }

        // H20: mensajería directa — puede el visitante enviar un DM a este jugador?
        $canDirectMessage = false;
        $existingDmConversation = null;
        if ($authUser && $authUser->id !== $player->id) {
            $canDirectMessage = $player->accepts_direct_messages && ! $player->hasBlocked($authUser);
            if ($canDirectMessage) {
                // Si ya existe una conversación directa, mostrar "Ver conversación" en vez de "Enviar mensaje".
                $existingDmConversation = Conversation::query()
                    ->whereNull('subject_type')
                    ->whereNull('subject_id')
                    ->whereHas('participants', fn ($q) => $q->where('user_id', $authUser->id)->whereNull('club_id'))
                    ->whereHas('participants', fn ($q) => $q->where('user_id', $player->id)->whereNull('club_id'))
                    ->first();
            }
        }

        return view('social.jugador.show', compact(
            'player', 'isSuspended', 'career', 'achievements', 'seasons',
            'openOpportunities', 'friendlyMetrics', 'reliabilityScore',
            'followersCount', 'isFollowing', 'sharedCount', 'viewerIsCaptain',
            'canDirectMessage', 'existingDmConversation',
        ));
    }
}
