<?php

namespace App\Services\Social;

use App\Models\Social\Conversation;
use App\Models\Social\ConversationParticipant;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Message;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FutGO Social — Fase 2 · Sesión S2-B · mensajería libre en conversaciones existentes.
 *
 * El esquema de conversaciones está diseñado desde S1-A. Esta sesión activa su uso:
 *
 *  - Una conversación SIEMPRE nace de un acuerdo previo (oportunidad aceptada o
 *    amistoso confirmado): no hay forma de iniciar un chat con un desconocido.
 *  - El primer mensaje es ESTRUCTURADO (lo genera el sistema, resume el acuerdo).
 *    A partir de ahí los participantes escriben mensajes LIBRES.
 *  - El acceso es estrictamente por participación: lo valida el controlador contra
 *    `hasParticipant()`, no la vista.
 *  - Compartir un dato de contacto (teléfono/WhatsApp) es una decisión explícita
 *    del usuario y queda como un mensaje libre más: el sistema no lo promueve.
 */
class ConversationService
{
    /** Longitud máxima de un mensaje libre. */
    public const MAX_BODY = 1000;

    // ─────────────────────────────────────────────────────────────────────
    // Creación automática (desde los servicios que cierran un acuerdo)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Asegura la conversación de una respuesta aceptada. Idempotente: si ya existe
     * la conversación del subject correspondiente, la devuelve sin duplicar.
     *
     * Para BUSCAR_RIVAL el acuerdo es un amistoso confirmado → la conversación se
     * vincula al amistoso (coordinación entre capitanes). En el resto se vincula a
     * la propia oportunidad (publicante ↔ respondente).
     */
    public function ensureForAcceptedResponse(Opportunity $opportunity, OpportunityResponse $response): Conversation
    {
        if ($opportunity->isBuscarRival()) {
            $friendly = $opportunity->friendlyMatch()->first();
            if ($friendly) {
                return $this->ensureForFriendly($friendly);
            }
        }

        return $this->ensureForOpportunity($opportunity, $response);
    }

    /** Conversación de una oportunidad (publicante ↔ respondente) + resumen del acuerdo. */
    public function ensureForOpportunity(Opportunity $opportunity, OpportunityResponse $response): Conversation
    {
        return DB::transaction(function () use ($opportunity, $response) {
            $conversation = Conversation::firstOrCreate([
                'subject_type' => 'opportunity',
                'subject_id'   => $opportunity->id,
            ]);

            $this->addParticipant($conversation, $opportunity->user_id, $opportunity->club_id);
            $this->addParticipant($conversation, $response->user_id, $response->club_id);

            $this->seedSystemMessage(
                $conversation,
                $this->summaryForOpportunity($opportunity, $response),
            );

            return $conversation;
        });
    }

    /** Conversación de un amistoso confirmado (los dos capitanes) + resumen. */
    public function ensureForFriendly(FriendlyMatch $match): Conversation
    {
        return DB::transaction(function () use ($match) {
            $conversation = Conversation::firstOrCreate([
                'subject_type' => 'friendly_match',
                'subject_id'   => $match->id,
            ]);

            $home = Club::find($match->home_club_id);
            $away = Club::find($match->away_club_id);

            $this->addParticipant($conversation, $home?->captain_user_id, $match->home_club_id);
            $this->addParticipant($conversation, $away?->captain_user_id, $match->away_club_id);

            $this->seedSystemMessage(
                $conversation,
                $this->summaryForFriendly($match, $home, $away),
            );

            return $conversation;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mensajería directa (H20)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Inicia o encuentra una conversación directa entre dos usuarios (sin acuerdo
     * previo). Solo posible si el destinatario acepta mensajes directos y no bloqueó
     * al remitente. Subject es NULL (distingue los DMs de las convs vinculadas).
     */
    public function initiateOrFindDirect(User $from, User $to): Conversation
    {
        // Buscar conversación directa existente entre los dos.
        $existing = Conversation::query()
            ->whereNull('subject_type')
            ->whereNull('subject_id')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $from->id)->whereNull('club_id'))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $to->id)->whereNull('club_id'))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($from, $to) {
            $conversation = Conversation::create(['last_message_at' => now()]);
            $this->addParticipant($conversation, $from->id, null);
            $this->addParticipant($conversation, $to->id, null);
            return $conversation;
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mensajería libre
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Publica un mensaje libre de `$sender`. El control de participación, longitud
     * y filtro de contenido lo hace el controlador; acá se persiste y se actualiza
     * el `last_message_at`, marcando el hilo como leído para el emisor.
     */
    public function postMessage(Conversation $conversation, User $sender, string $body, ?int $asClubId = null): Message
    {
        return DB::transaction(function () use ($conversation, $sender, $body, $asClubId) {
            $message = $conversation->messages()->create([
                'sender_user_id' => $sender->id,
                'sender_club_id' => $asClubId,
                'body'           => $body,
                'type'           => Message::TYPE_FREE,
            ]);

            $conversation->forceFill(['last_message_at' => $message->created_at])->save();

            // Quien escribe ya "leyó" hasta su propio mensaje.
            $this->markRead($conversation, $sender);

            return $message;
        });
    }

    /** Marca el hilo como leído para el usuario (mueve su `last_read_at`). */
    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()
            ->where('user_id', $user->id)
            ->update(['last_read_at' => now()]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Lectura (lista / detalle)
    // ─────────────────────────────────────────────────────────────────────

    /** Conversaciones del usuario, recientes primero, con contexto y último mensaje. */
    public function forUser(User $user): Collection
    {
        return Conversation::query()
            ->forUser($user)
            ->with(['subject', 'latestMessage', 'participants.user:id,name,futgo_id,avatar_url', 'participants.club:id,name,slug,shield_url'])
            ->get();
    }

    /** ¿Hay mensajes posteriores a la última lectura del usuario en esta conversación? */
    public function hasUnread(Conversation $conversation, User $user): bool
    {
        $participant = $conversation->participantFor($user);
        if ($participant === null) {
            return false;
        }

        $last = $conversation->last_message_at;
        if ($last === null) {
            return false;
        }

        return $participant->last_read_at === null || $participant->last_read_at->lessThan($last);
    }

    /** Conversaciones del usuario con al menos un mensaje sin leer (para el badge del navbar). */
    public function unreadCount(User $user): int
    {
        return Conversation::query()
            ->whereHas('participants', function ($q) use ($user) {
                $q->where('user_id', $user->id)
                  ->where(function ($w) {
                      $w->whereNull('last_read_at')
                        ->orWhereColumn('conversation_participants.last_read_at', '<', 'conversations.last_message_at');
                  });
            })
            ->whereNotNull('last_message_at')
            ->count();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Agrega un participante (idempotente). Requiere user_id: el club es solo el
     * rol que el usuario representa en el hilo. Sin usuario, no se agrega nada (un
     * club sin capitán con cuenta no puede chatear).
     */
    private function addParticipant(Conversation $conversation, ?int $userId, ?int $clubId): void
    {
        if ($userId === null) {
            return;
        }

        ConversationParticipant::firstOrCreate(
            [
                'conversation_id' => $conversation->id,
                'user_id'         => $userId,
                'club_id'         => $clubId,
            ],
            ['last_read_at' => null],
        );
    }

    /**
     * Inserta el mensaje estructurado inicial una sola vez (al crear el hilo). Lo
     * genera el sistema: sin emisor humano (queda como `isSystem()`).
     */
    private function seedSystemMessage(Conversation $conversation, string $body): void
    {
        if ($conversation->messages()->exists()) {
            return;
        }

        $message = $conversation->messages()->create([
            'sender_user_id' => null,
            'sender_club_id' => null,
            'body'           => $body,
            'type'           => Message::TYPE_STRUCTURED,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();
    }

    /** Resumen del acuerdo de una oportunidad aceptada (voseo). */
    private function summaryForOpportunity(Opportunity $opportunity, OpportunityResponse $response): string
    {
        $owner     = $opportunity->authorName();
        $responder = $response->club?->name ?? $response->user?->name ?? 'el otro participante';

        return match ($opportunity->type) {
            Opportunity::TYPE_BUSCAR_JUGADOR  => "{$responder} se sumó a {$owner}. Coordina los próximos pasos por acá.",
            Opportunity::TYPE_BUSCAR_REFUERZO => "{$responder} fue aceptado como refuerzo de {$owner}. Coordina los detalles por acá.",
            Opportunity::TYPE_BUSCAR_EQUIPO   => "{$responder} sumó a {$owner} a su plantilla. Coordina los detalles por acá.",
            default                            => "Se aceptó la conexión entre {$owner} y {$responder}. Coordina los detalles por acá.",
        };
    }

    /** Resumen de coordinación de un amistoso confirmado. */
    private function summaryForFriendly(FriendlyMatch $match, ?Club $home, ?Club $away): string
    {
        $when = $match->scheduled_at?->format('d/m/Y H:i');
        $base = 'Amistoso confirmado: ' . ($home?->name ?? 'Local') . ' vs ' . ($away?->name ?? 'Visitante') . '.';
        $base .= $when ? " Fecha propuesta: {$when}." : '';

        return $base . ' Coordina horario, cancha y equipaciones por acá.';
    }
}
