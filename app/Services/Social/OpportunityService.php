<?php

namespace App\Services\Social;

use App\Exceptions\Social\OpportunityException;
use App\Models\Social\FeedEvent;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\User;
use App\Services\Torneos\ClubMembershipService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * FutGO Social — Fase 1 · servicio del flujo de Oportunidades (Sesiones S1-B / S1-D).
 *
 * Es el corazón funcional del módulo Social: publicar, responder, aceptar
 * (generando la entidad concreta según el tipo), rechazar, contraproponer,
 * cancelar y vencer oportunidades.
 *
 * Reglas clave:
 *  - Al ACEPTAR, según el tipo se crea una entidad distinta (FriendlyMatch,
 *    fila en club_players o asignación puntual). Toda la operación va en una
 *    transacción: si falla la creación de la entidad, la oportunidad NO se cierra.
 *  - "Una sola aceptada": para RIVAL/REFUERZO/EQUIPO aceptar cierra la
 *    oportunidad, de modo que un segundo accept queda bloqueado por el guard de
 *    estado. BUSCAR_JUGADOR es la excepción: admite varias aceptadas hasta agotar
 *    los cupos.
 *  - Cancelar dentro de las 24h previas a un amistoso confirmado genera un
 *    reliability_event de cancelacion_tardia (penaliza confiabilidad).
 */
class OpportunityService
{
    public function __construct(
        private ClubMembershipService $membership,
        private FriendlyMatchService $friendlyMatches,
        private ReliabilityService $reliability,
        private FeedService $feed,
        private ConversationService $conversations,
    ) {}

    // ─────────────────────────────────────────────────────────────────────
    // 1. Publicar
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Publica una oportunidad en nombre de `$author` (siempre un usuario
     * registrado: los "por verificar" sin cuenta no pueden autenticarse).
     *
     * $data esperado:
     *   type, city, zone?, required_level, window_start?, window_end?,
     *   expires_at?, payload (array), club_id? (para tipos de club).
     */
    public function publish(User $author, array $data): Opportunity
    {
        $type = $data['type'] ?? null;
        if (! in_array($type, Opportunity::TYPES, true)) {
            throw OpportunityException::make('Tipo de oportunidad inválido.');
        }

        // S1-F: usuario suspendido por moderación no puede publicar.
        if ($author->isSuspended()) {
            throw OpportunityException::suspended();
        }

        // S1-D: jugador pausado no puede publicar nuevas oportunidades.
        if ($this->reliability->isPaused('user', $author->id)) {
            throw OpportunityException::paused();
        }

        // Para tipos de club verificamos la pausa también sobre el club.
        if ($type !== Opportunity::TYPE_BUSCAR_EQUIPO) {
            $clubIdForPause = $data['club_id'] ?? null;
            if ($clubIdForPause && $this->reliability->isPaused('club', (int) $clubIdForPause)) {
                throw OpportunityException::paused();
            }
        }

        $attributes = [
            'type'           => $type,
            'city'           => $data['city'],
            'zone'           => $data['zone'] ?? null,
            'venue_id'       => isset($data['venue_id']) ? (int) $data['venue_id'] : null,
            'required_level' => $data['required_level'] ?? null,
            'window_start'   => $data['window_start'] ?? null,
            'window_end'     => $data['window_end'] ?? null,
            'status'         => Opportunity::STATUS_ABIERTA,
            'is_express'     => (bool) ($data['is_express'] ?? false),
            'payload'        => $data['payload'] ?? [],
        ];

        // El creador depende del tipo: club (RIVAL/JUGADOR/REFUERZO) o jugador (EQUIPO).
        if ($type === Opportunity::TYPE_BUSCAR_EQUIPO) {
            $attributes['user_id'] = $author->id;
            $attributes['club_id'] = null;
        } else {
            $club = $this->requireCaptainedClub($author, $data['club_id'] ?? null);
            $attributes['user_id'] = $author->id;
            $attributes['club_id'] = $club->id;
        }

        // Vigencia: si no se indicó, deriva del fin (o inicio) de la ventana.
        $attributes['expires_at'] = $data['expires_at']
            ?? $attributes['window_end']
            ?? $attributes['window_start']
            ?? null;

        $opportunity = Opportunity::create($attributes);

        // Feed: una nueva oportunidad se difunde a su ciudad/nivel y a los
        // seguidores del publicante (no bloqueante).
        $author = $opportunity->club ?? $opportunity->user;
        $this->feed->record(FeedEvent::TYPE_OPORTUNIDAD_PUBLICADA, $author, $opportunity, [
            'city'    => $opportunity->city,
            'level'   => $opportunity->required_level,
            'payload' => [
                'opportunity_id' => $opportunity->id,
                'type'           => $opportunity->type,
                'type_label'     => $opportunity->typeLabel(),
                'author'         => $opportunity->authorName(),
                'city'           => $opportunity->city,
                'express'        => $opportunity->isExpress(),
            ],
        ]);

        return $opportunity;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Responder
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Registra una respuesta de `$responder` a la oportunidad.
     *
     * Reglas: la oportunidad debe poder recibir respuestas; no se responde la
     * propia; el actor responde como club (RIVAL/EQUIPO) o como jugador
     * (JUGADOR/REFUERZO). No duplica respuesta pendiente del mismo actor.
     */
    public function respond(Opportunity $opportunity, User $responder, array $data): OpportunityResponse
    {
        if ($responder->isSuspended()) {
            throw OpportunityException::suspended();
        }

        if (! $opportunity->canReceiveResponses()) {
            throw OpportunityException::make('Esta oportunidad ya no admite respuestas.');
        }

        if ($this->isOwner($opportunity, $responder)) {
            throw OpportunityException::make('No puedes responder tu propia oportunidad.');
        }

        $clubId = null;
        if ($this->respondsAsClub($opportunity)) {
            $club   = $this->requireCaptainedClub($responder, $data['club_id'] ?? null);
            $clubId = $club->id;
        }

        // Evita respuestas duplicadas todavía pendientes del mismo actor.
        $already = $opportunity->responses()
            ->where('status', OpportunityResponse::STATUS_PENDIENTE)
            ->where('user_id', $responder->id)
            ->when($clubId, fn ($q) => $q->where('club_id', $clubId))
            ->exists();
        if ($already) {
            throw OpportunityException::make('Ya enviaste una respuesta a esta oportunidad.');
        }

        $response = $opportunity->responses()->create([
            'user_id' => $responder->id,
            'club_id' => $clubId,
            'message' => $data['message'] ?? null,
            'status'  => OpportunityResponse::STATUS_PENDIENTE,
        ]);

        // Notifica al dueño de la oportunidad vía Feed (no bloqueante).
        $this->feed->record(
            FeedEvent::TYPE_OPORTUNIDAD_RESPONDIDA,
            $responder,
            $opportunity,
            [
                'city'    => $opportunity->city,
                'payload' => [
                    'opportunity_id'   => $opportunity->id,
                    'opportunity_type' => $opportunity->typeLabel(),
                    'responder_name'   => $responder->name,
                ],
            ]
        );

        return $response;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Aceptar (genera la entidad concreta según el tipo) — TRANSACCIONAL
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Acepta una respuesta. Toda la operación va en una transacción: si falla la
     * creación de la entidad concreta, la oportunidad no queda cerrada.
     */
    public function accept(OpportunityResponse $response): OpportunityResponse
    {
        $result = DB::transaction(function () use ($response) {
            // Bloqueo pesimista para serializar aceptaciones concurrentes.
            $opportunity = Opportunity::whereKey($response->opportunity_id)->lockForUpdate()->firstOrFail();

            if (! $opportunity->isAbierta() && ! $opportunity->isEnNegociacion()) {
                throw OpportunityException::make('La oportunidad ya no está abierta.');
            }
            if ($opportunity->isVencida()) {
                throw OpportunityException::make('La oportunidad está vencida.');
            }
            if (! in_array($response->status, [OpportunityResponse::STATUS_PENDIENTE, OpportunityResponse::STATUS_CONTRAPROPUESTA], true)) {
                throw OpportunityException::make('Esa respuesta no se puede aceptar.');
            }

            match ($opportunity->type) {
                Opportunity::TYPE_BUSCAR_RIVAL    => $this->acceptRival($opportunity, $response),
                Opportunity::TYPE_BUSCAR_JUGADOR  => $this->acceptJugador($opportunity, $response),
                Opportunity::TYPE_BUSCAR_REFUERZO => $this->acceptRefuerzo($opportunity, $response),
                Opportunity::TYPE_BUSCAR_EQUIPO   => $this->acceptEquipo($opportunity, $response),
                default => throw OpportunityException::make('Tipo de oportunidad no soportado.'),
            };

            $response->update(['status' => OpportunityResponse::STATUS_ACEPTADA]);

            // S2-B: el acuerdo abre una conversación entre los dos actores (con un
            // primer mensaje estructurado que lo resume). Va dentro de la misma
            // transacción: si algo falla, no queda media aceptación.
            $this->conversations->ensureForAcceptedResponse($opportunity->refresh(), $response);

            return $response->refresh();
        });

        // Feed (post-commit, no bloqueante): la oportunidad fue aceptada. El actor
        // es el publicante y el subject el respondente — seguir a cualquiera la
        // vuelve relevante. Para BUSCAR_RIVAL, además se confirmó un amistoso.
        $this->recordAcceptanceFeed($result);

        return $result;
    }

    /** Eventos de Feed al aceptar una respuesta (post-commit). */
    private function recordAcceptanceFeed(OpportunityResponse $response): void
    {
        $opportunity = $response->opportunity()->first();
        if ($opportunity === null) {
            return;
        }

        $owner     = $opportunity->club ?? $opportunity->user;
        $responder = $response->club ?? $response->user;

        $this->feed->record(FeedEvent::TYPE_OPORTUNIDAD_ACEPTADA, $owner, $responder, [
            'payload' => [
                'opportunity_id' => $opportunity->id,
                'type'           => $opportunity->type,
                'type_label'     => $opportunity->typeLabel(),
                'owner'          => $opportunity->authorName(),
                'responder'      => $responder?->name ?? '—',
            ],
        ]);

        // BUSCAR_RIVAL cierra con un amistoso confirmado entre los dos clubs.
        if ($opportunity->isBuscarRival()) {
            $friendly = $opportunity->friendlyMatch()->first();
            if ($friendly) {
                $this->feed->record(
                    FeedEvent::TYPE_AMISTOSO_CONFIRMADO,
                    $friendly->homeClub()->first(),
                    $friendly->awayClub()->first(),
                    [
                        'city'    => $opportunity->city,
                        'level'   => $opportunity->required_level,
                        'payload' => [
                            'friendly_match_id' => $friendly->id,
                            'home'              => $opportunity->club?->name,
                            'away'              => $responder?->name,
                            'scheduled_at'      => optional($friendly->scheduled_at)->toIso8601String(),
                        ],
                    ]
                );
            }
        }
    }

    /** BUSCAR_RIVAL → crea un FriendlyMatch confirmado y cierra la oportunidad. */
    private function acceptRival(Opportunity $opportunity, OpportunityResponse $response): void
    {
        if (! $opportunity->club_id) {
            throw OpportunityException::make('La oportunidad no tiene club publicante.');
        }
        if (! $response->club_id) {
            throw OpportunityException::make('La respuesta no indica el club rival.');
        }

        FriendlyMatch::create([
            'opportunity_id' => $opportunity->id,
            'home_club_id'   => $opportunity->club_id,
            'away_club_id'   => $response->club_id,
            'scheduled_at'   => $opportunity->window_start,
            'location'       => $opportunity->payload['cancha_propuesta'] ?? null,
            'venue_id'       => $opportunity->venue_id,
            'status'         => FriendlyMatch::STATUS_CONFIRMADO,
        ]);

        $opportunity->update(['status' => Opportunity::STATUS_CERRADA]);
    }

    /**
     * BUSCAR_JUGADOR → agrega al jugador respondente a la plantilla permanente
     * del club publicante. Descuenta un cupo; si quedan cupos, la oportunidad
     * sigue abierta (puede aceptar varios).
     */
    private function acceptJugador(Opportunity $opportunity, OpportunityResponse $response): void
    {
        if (! $opportunity->club_id) {
            throw OpportunityException::make('La oportunidad no tiene club publicante.');
        }
        $player = $response->user;
        if (! $player) {
            throw OpportunityException::make('La respuesta no indica el jugador.');
        }

        $this->addPlayerToClub(Club::findOrFail($opportunity->club_id), $player, $opportunity->payload['posicion'] ?? null);

        // Descuenta cupo (default 1). Cierra cuando se agotan.
        $payload = $opportunity->payload ?? [];
        $cupos   = (int) ($payload['cupos'] ?? 1);
        $cupos   = max(0, $cupos - 1);
        $payload['cupos'] = $cupos;

        $opportunity->update([
            'payload' => $payload,
            'status'  => $cupos <= 0 ? Opportunity::STATUS_CERRADA : Opportunity::STATUS_ABIERTA,
        ]);
    }

    /**
     * BUSCAR_REFUERZO → registra la asignación PUNTUAL (no toca la plantilla
     * permanente) en el payload y cierra la oportunidad.
     */
    private function acceptRefuerzo(Opportunity $opportunity, OpportunityResponse $response): void
    {
        if (! $response->user_id) {
            throw OpportunityException::make('La respuesta no indica el jugador refuerzo.');
        }

        $payload = $opportunity->payload ?? [];
        $payload['assignment'] = [
            'user_id'     => $response->user_id,
            'response_id' => $response->id,
            'assigned_at' => now()->toIso8601String(),
        ];

        $opportunity->update([
            'payload' => $payload,
            'status'  => Opportunity::STATUS_CERRADA,
        ]);
    }

    /**
     * BUSCAR_EQUIPO → el club respondente convoca al jugador publicante: lo
     * suma a su plantilla permanente (invitación) y cierra la oportunidad.
     * Simétrico a BUSCAR_JUGADOR, dirección inversa.
     */
    private function acceptEquipo(Opportunity $opportunity, OpportunityResponse $response): void
    {
        if (! $response->club_id) {
            throw OpportunityException::make('La respuesta no indica el club que convoca.');
        }
        $player = $opportunity->user;
        if (! $player) {
            throw OpportunityException::make('La oportunidad no tiene jugador publicante.');
        }

        $this->addPlayerToClub(Club::findOrFail($response->club_id), $player, $opportunity->payload['posicion'] ?? null);

        $opportunity->update(['status' => Opportunity::STATUS_CERRADA]);
    }

    /** Agrega un jugador registrado a la plantilla permanente (idempotente). */
    private function addPlayerToClub(Club $club, User $player, ?string $position): void
    {
        if ($club->players()->where('user_id', $player->id)->exists()) {
            return; // ya es parte de la plantilla
        }

        $member = ClubPlayer::create([
            'club_id'             => $club->id,
            'user_id'             => $player->id,
            'verification_status' => 'registrado',
            'position'            => $position,
            'status'              => 'active',
        ]);

        // Propaga la alta a las participaciones por torneo no finalizadas.
        $this->membership->syncMemberAdded($member);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Rechazar / contraproponer
    // ─────────────────────────────────────────────────────────────────────

    public function reject(OpportunityResponse $response): void
    {
        $response->update(['status' => OpportunityResponse::STATUS_RECHAZADA]);
    }

    /** Deja una contrapropuesta: la respuesta sigue viva, con nuevo mensaje. */
    public function counter(OpportunityResponse $response, ?string $message = null): void
    {
        $response->update([
            'status'  => OpportunityResponse::STATUS_CONTRAPROPUESTA,
            'message' => $message ?? $response->message,
        ]);

        // La negociación abierta refleja el estado de la oportunidad.
        $opportunity = $response->opportunity;
        if ($opportunity && $opportunity->isAbierta()) {
            $opportunity->update(['status' => Opportunity::STATUS_EN_NEGOCIACION]);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Cancelar (con reliability_event si es tardío)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cancela una oportunidad propia con motivo. Si tenía un amistoso confirmado
     * y la cancelación cae dentro de las 24h previas al partido, genera un
     * reliability_event de cancelacion_tardia y cancela el amistoso.
     */
    public function cancel(Opportunity $opportunity, string $reason): Opportunity
    {
        return DB::transaction(function () use ($opportunity, $reason) {
            $friendly = $opportunity->friendlyMatch()->where('status', FriendlyMatch::STATUS_CONFIRMADO)->first();

            // Cancelable si está abierta/en negociación, o si quedó cerrada por un
            // amistoso confirmado (BUSCAR_RIVAL) — ese es el caso que dispara la
            // penalización por cancelación tardía. Las cerradas sin amistoso
            // (jugador/refuerzo/equipo ya resueltos) y las vencidas no se cancelan.
            $cancelable = in_array($opportunity->status, [Opportunity::STATUS_ABIERTA, Opportunity::STATUS_EN_NEGOCIACION], true)
                || ($opportunity->isCerrada() && $friendly !== null);

            if (! $cancelable) {
                throw OpportunityException::make('Esta oportunidad ya no se puede cancelar.');
            }

            // La cancelación del amistoso (y la penalización por tardía) la maneja
            // FriendlyMatchService: única fuente de verdad del ciclo de vida del
            // amistoso. El club que cancela es el publicante de la oportunidad.
            if ($friendly && $opportunity->club_id) {
                $club = Club::find($opportunity->club_id);
                if ($club) {
                    $this->friendlyMatches->cancel($friendly, $club, $reason);
                }
            }

            $payload = $opportunity->payload ?? [];
            $payload['cancellation_reason'] = $reason;

            $opportunity->update([
                'status'  => Opportunity::STATUS_CANCELADA,
                'payload' => $payload,
            ]);

            return $opportunity->refresh();
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Vencimiento automático (scheduler)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Marca como `vencida` toda oportunidad activa cuya vigencia ya pasó.
     * La "vigencia" es expires_at, o en su defecto el fin/inicio de la ventana.
     * Devuelve la cantidad de oportunidades vencidas.
     */
    public function expireDue(?Carbon $now = null): int
    {
        $now = $now ?? now();
        $count = 0;

        Opportunity::query()
            ->whereIn('status', [Opportunity::STATUS_ABIERTA, Opportunity::STATUS_EN_NEGOCIACION])
            ->orderBy('id')
            ->chunkById(200, function ($opportunities) use ($now, &$count) {
                foreach ($opportunities as $opportunity) {
                    $deadline = $opportunity->expires_at
                        ?? $opportunity->window_end
                        ?? $opportunity->window_start;

                    if ($deadline !== null && $deadline->lessThan($now)) {
                        $opportunity->update(['status' => Opportunity::STATUS_VENCIDA]);
                        $count++;
                    }
                }
            });

        return $count;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers de identidad / elegibilidad
    // ─────────────────────────────────────────────────────────────────────

    /** ¿El usuario es dueño de la oportunidad (la publicó o capitanea su club)? */
    public function isOwner(Opportunity $opportunity, User $user): bool
    {
        if ($opportunity->user_id && $opportunity->user_id === $user->id) {
            return true;
        }
        if ($opportunity->club_id) {
            return Club::whereKey($opportunity->club_id)
                ->where('captain_user_id', $user->id)
                ->exists();
        }

        return false;
    }

    /** RIVAL y EQUIPO se responden como club; JUGADOR y REFUERZO como jugador. */
    private function respondsAsClub(Opportunity $opportunity): bool
    {
        return in_array($opportunity->type, [
            Opportunity::TYPE_BUSCAR_RIVAL,
            Opportunity::TYPE_BUSCAR_EQUIPO,
        ], true);
    }

    /**
     * Resuelve el club que capitanea el usuario para actuar en su nombre.
     * Si pasa $clubId valida que lo capitanee; si no, exige que tenga exactamente
     * uno (si tiene varios, debe elegir).
     */
    private function requireCaptainedClub(User $user, ?int $clubId): Club
    {
        if ($clubId) {
            $club = Club::whereKey($clubId)->where('captain_user_id', $user->id)->first();
            if (! $club) {
                throw OpportunityException::make('Solo el capitán del equipo puede actuar en su nombre.');
            }

            return $club;
        }

        $clubs = Club::where('captain_user_id', $user->id)->get();
        if ($clubs->isEmpty()) {
            throw OpportunityException::make('Necesitas ser capitán de un equipo para esta acción.');
        }
        if ($clubs->count() > 1) {
            throw OpportunityException::make('Elige con qué equipo quieres participar.');
        }

        return $clubs->first();
    }
}
