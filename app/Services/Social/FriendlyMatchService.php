<?php

namespace App\Services\Social;

use App\Exceptions\Social\FriendlyMatchException;
use App\Models\Social\FeedEvent;
use App\Models\Social\FriendlyMatch;
use App\Models\Social\ReliabilityEvent;
use App\Models\Torneos\Club;
use App\Models\Torneos\ClubPlayer;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\PlayerCareerStat;
use App\Models\Torneos\TeamPlayer;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * FutGO Social — Fase 1 · Sesión S1-C · ciclo de vida del amistoso.
 *
 * Doble confirmación de resultado (cada capitán reporta de forma independiente),
 * disputa cuando difieren, rectificación, escalamiento y resolución por admin,
 * cancelación con penalización por cancelación tardía. Más métricas read-time
 * para los perfiles (jugador/club), siguiendo el patrón de agregación en lectura
 * ya usado para las stats de club (no hay tabla de cache propia: el historial de
 * amistosos se deriva directamente de `friendly_matches`).
 */
class FriendlyMatchService
{
    /** Ventana (horas) que define una cancelación como "tardía". */
    public const LATE_CANCEL_HOURS = 24;

    public function __construct(private FeedService $feed) {}

    // ─────────────────────────────────────────────────────────────────────
    // 1. Reportar resultado (doble confirmación) — ATÓMICO
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Un capitán reporta el marcador de su equipo. Si ambos capitanes reportan
     * el mismo marcador, el partido queda `jugado`/`acordado` (el resultado se
     * asienta en el historial — derivado read-time — en la MISMA transacción que
     * fija el estado). Si difieren, pasa a `en_disputa`.
     *
     * Sirve también para RECTIFICAR: re-reportar mientras está `en_disputa`.
     */
    public function reportResult(FriendlyMatch $match, Club $reporter, int $homeScore, int $awayScore): FriendlyMatch
    {
        $match = DB::transaction(function () use ($match, $reporter, $homeScore, $awayScore) {
            $match = FriendlyMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! in_array($match->status, [FriendlyMatch::STATUS_CONFIRMADO, FriendlyMatch::STATUS_EN_DISPUTA], true)) {
                throw FriendlyMatchException::make('Este amistoso ya no admite carga de resultado.');
            }

            $side = $match->sideForClub($reporter->id);
            if ($side === null) {
                throw FriendlyMatchException::make('Solo un equipo participante puede cargar el resultado.');
            }

            if ($side === 'home') {
                $match->home_reported_home_score = $homeScore;
                $match->home_reported_away_score = $awayScore;
                $match->home_reported_at         = now();
            } else {
                $match->away_reported_home_score = $homeScore;
                $match->away_reported_away_score = $awayScore;
                $match->away_reported_at         = now();
            }

            // Recalcula el acuerdo a partir de ambos reportes. Si ahora coinciden,
            // fija final_* + status `jugado`; si difieren, status `en_disputa`.
            $match->applyAgreementFromReports();

            // Si se llegó a acuerdo, una eventual disputa/escala previa queda saldada.
            if ($match->hasAgreedResult()) {
                $match->escalated_at         = null;
                $match->escalated_by_club_id = null;
            }

            $match->save();

            return $match;
        });

        // Feed (post-commit, no bloqueante): si quedó jugado, es un resultado.
        if ($match->isJugado()) {
            $this->recordResultFeed($match);
        }

        return $match;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Escalar disputa a un admin
    // ─────────────────────────────────────────────────────────────────────

    public function escalate(FriendlyMatch $match, Club $by): FriendlyMatch
    {
        if (! $match->estaEnDisputa()) {
            throw FriendlyMatchException::make('Solo un amistoso en disputa puede escalarse.');
        }
        if ($match->sideForClub($by->id) === null) {
            throw FriendlyMatchException::make('Solo un equipo participante puede escalar la disputa.');
        }

        $match->update([
            'escalated_at'         => now(),
            'escalated_by_club_id' => $by->id,
        ]);

        return $match;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 3. Resolución por admin (resultado oficial)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Un admin de plataforma resuelve una disputa fijando el resultado oficial.
     * Cierra la disputa: el partido queda `jugado`/`acordado`.
     */
    public function resolveByAdmin(FriendlyMatch $match, User $admin, int $homeScore, int $awayScore): FriendlyMatch
    {
        $match = DB::transaction(function () use ($match, $admin, $homeScore, $awayScore) {
            $match = FriendlyMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $match->estaEnDisputa()) {
                throw FriendlyMatchException::make('Solo un amistoso en disputa puede resolverse por admin.');
            }

            $match->update([
                'final_home_score'    => $homeScore,
                'final_away_score'    => $awayScore,
                'result_agreement'    => FriendlyMatch::AGREEMENT_ACORDADO,
                'status'              => FriendlyMatch::STATUS_JUGADO,
                'resolved_by_user_id' => $admin->id,
                'resolved_at'         => now(),
            ]);

            return $match;
        });

        // Feed (post-commit, no bloqueante): el admin fijó el resultado oficial.
        $this->recordResultFeed($match);

        return $match;
    }

    /**
     * Feed: resultado de amistoso. Conecta a los dos clubs (actor=local,
     * subject=visitante): seguir a cualquiera lo vuelve relevante. No bloqueante.
     */
    private function recordResultFeed(FriendlyMatch $match): void
    {
        $home = $match->homeClub()->first();
        $away = $match->awayClub()->first();

        $this->feed->record(FeedEvent::TYPE_RESULTADO_AMISTOSO, $home, $away, [
            'payload' => [
                'friendly_match_id' => $match->id,
                'home'              => $home?->name,
                'away'              => $away?->name,
                'home_score'        => $match->final_home_score,
                'away_score'        => $match->final_away_score,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // 4. Cancelar (con reliability_event si es tardío)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Cancela un amistoso confirmado. Si la cancelación cae dentro de las 24h
     * previas a la fecha acordada, genera un reliability_event de
     * cancelacion_tardia para el club que canceló (más de 24h: sin penalización).
     */
    public function cancel(FriendlyMatch $match, Club $by, string $reason): FriendlyMatch
    {
        return DB::transaction(function () use ($match, $by, $reason) {
            $match = FriendlyMatch::whereKey($match->id)->lockForUpdate()->firstOrFail();

            if (! $match->isConfirmado()) {
                throw FriendlyMatchException::make('Solo un amistoso confirmado puede cancelarse.');
            }
            if ($match->sideForClub($by->id) === null) {
                throw FriendlyMatchException::make('Solo un equipo participante puede cancelar el amistoso.');
            }

            if ($this->isLateCancellation($match->scheduled_at)) {
                $by->reliabilityEvents()->create([
                    'type'              => ReliabilityEvent::TYPE_CANCELACION_TARDIA,
                    'friendly_match_id' => $match->id,
                    'meta'              => ['reason' => $reason],
                    'occurred_at'       => now(),
                ]);
            }

            $match->update([
                'status'               => FriendlyMatch::STATUS_CANCELADO,
                'cancelled_by_club_id' => $by->id,
                'cancellation_reason'  => $reason,
                'cancelled_at'         => now(),
            ]);

            return $match;
        });
    }

    /** ¿`$scheduledAt` cae dentro de la ventana de cancelación tardía (futuro y ≤24h)? */
    public function isLateCancellation(?Carbon $scheduledAt): bool
    {
        if (! $scheduledAt) {
            return false;
        }

        return $scheduledAt->isFuture()
            && now()->greaterThanOrEqualTo($scheduledAt->copy()->subHours(self::LATE_CANCEL_HOURS));
    }

    // ─────────────────────────────────────────────────────────────────────
    // 5. Consultas de listado (capitán / admin)
    // ─────────────────────────────────────────────────────────────────────

    /** Amistosos de los clubs indicados, con los clubs eager-cargados. */
    public function forClubs(array $clubIds): Collection
    {
        if (empty($clubIds)) {
            return collect();
        }

        return FriendlyMatch::query()
            ->involvingAnyClub($clubIds)
            ->with([
                'homeClub:id,name,slug,shield_url',
                'awayClub:id,name,slug,shield_url',
                'opportunity:id',
                'venue:id,name,slug',
            ])
            ->orderByRaw('scheduled_at IS NULL')
            ->orderBy('scheduled_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Amistosos en disputa (admin) — escalados primero. */
    public function disputed(): Collection
    {
        return FriendlyMatch::query()
            ->where('status', FriendlyMatch::STATUS_EN_DISPUTA)
            ->with(['homeClub:id,name,slug,shield_url', 'awayClub:id,name,slug,shield_url', 'escalatedByClub:id,name'])
            ->orderByRaw('escalated_at IS NULL')   // escalados primero
            ->orderByDesc('escalated_at')
            ->orderByDesc('id')
            ->get();
    }

    /** Historial de cancelaciones (admin). */
    public function cancellations(): Collection
    {
        return FriendlyMatch::query()
            ->where('status', FriendlyMatch::STATUS_CANCELADO)
            ->with(['homeClub:id,name', 'awayClub:id,name', 'cancelledByClub:id,name'])
            ->orderByDesc('cancelled_at')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    // 6. Métricas read-time para los perfiles
    // ─────────────────────────────────────────────────────────────────────

    /** Ids de clubs a los que pertenece el usuario (plantilla + capitanía). */
    public function userClubIds(User $user): array
    {
        $fromMembership = ClubPlayer::where('user_id', $user->id)->pluck('club_id');
        $fromCaptaincy  = Club::where('captain_user_id', $user->id)->pluck('id');

        return $fromMembership->merge($fromCaptaincy)->unique()->values()->all();
    }

    /**
     * Amistosos JUGADOS del usuario (a través de sus clubs), con la perspectiva
     * resuelta: club propio, rival, marcador a favor/en contra y resultado.
     */
    public function userFriendlies(User $user): Collection
    {
        $clubIds = $this->userClubIds($user);
        if (empty($clubIds)) {
            return collect();
        }

        return FriendlyMatch::jugados()
            ->involvingAnyClub($clubIds)
            ->with(['homeClub:id,name,slug,shield_url', 'awayClub:id,name,slug,shield_url'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($fm) => $this->perspective($fm, in_array($fm->home_club_id, $clubIds, true) ? $fm->home_club_id : $fm->away_club_id));
    }

    /** Métricas sociales del jugador (total partidos, % presentación, récord amistoso). */
    public function userMetrics(User $user, ?PlayerCareerStat $careerStat = null): array
    {
        $tournamentMatches = (int) ($careerStat->matches_played ?? 0);

        $friendlies = $this->userFriendlies($user);
        $record     = $this->aggregateRecord($friendlies);

        // Presentación = partidos de torneo jugados / convocatorias aceptadas.
        $tpIds = TeamPlayer::where('user_id', $user->id)->pluck('id');
        $accepted = $tpIds->isEmpty()
            ? 0
            : MatchCallUp::whereIn('team_player_id', $tpIds)->where('status', 'confirmado')->count();
        $presentation = $accepted > 0 ? min(100, (int) round($tournamentMatches / $accepted * 100)) : null;

        return [
            'total_matches'      => $tournamentMatches + $friendlies->count(),
            'tournament_matches' => $tournamentMatches,
            'friendlies_played'  => $friendlies->count(),
            'accepted_callups'   => $accepted,
            'presentation_pct'   => $presentation,
            'friendly_record'    => $record,
        ];
    }

    /** Amistosos JUGADOS de un club, con perspectiva resuelta. */
    public function clubFriendlies(Club $club): Collection
    {
        return FriendlyMatch::jugados()
            ->involvingClub($club->id)
            ->with(['homeClub:id,name,slug,shield_url', 'awayClub:id,name,slug,shield_url'])
            ->orderByDesc('scheduled_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($fm) => $this->perspective($fm, $club->id));
    }

    /**
     * S3-A · Historial de compatibilidad (head-to-head): rivales con los que el
     * club YA jugó amistosos, cuántas veces y con qué récord. Derivado del
     * historial de FriendlyMatch (sin tabla nueva), agrupando las perspectivas
     * de `clubFriendlies` por rival. Ordenado por cantidad de enfrentamientos.
     *
     * @return \Illuminate\Support\Collection<int,object> {opponent, count, won, drawn, lost}
     */
    public function clubHeadToHead(Club $club): Collection
    {
        return $this->clubFriendlies($club)
            ->filter(fn ($p) => $p->opponent !== null)
            ->groupBy(fn ($p) => $p->opponent->id)
            ->map(function (Collection $rows) {
                $first = $rows->first();

                return (object) [
                    'opponent' => $first->opponent,
                    'count'    => $rows->count(),
                    'won'      => $rows->where('outcome', 'V')->count(),
                    'drawn'    => $rows->where('outcome', 'E')->count(),
                    'lost'     => $rows->where('outcome', 'D')->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();
    }

    /**
     * Cantidad de amistosos JUGADOS entre dos clubs (head-to-head directo).
     * Útil para mostrar "ya jugaron X veces" en el cruce de dos perfiles.
     */
    public function headToHeadCount(int $clubA, int $clubB): int
    {
        return FriendlyMatch::jugados()
            ->where(function ($q) use ($clubA, $clubB) {
                $q->where(function ($q2) use ($clubA, $clubB) {
                    $q2->where('home_club_id', $clubA)->where('away_club_id', $clubB);
                })->orWhere(function ($q2) use ($clubA, $clubB) {
                    $q2->where('home_club_id', $clubB)->where('away_club_id', $clubA);
                });
            })
            ->count();
    }

    /** Métricas de amistosos de un club. */
    public function clubMetrics(Club $club): array
    {
        $friendlies = $this->clubFriendlies($club);
        $record     = $this->aggregateRecord($friendlies);
        $played     = $friendlies->count();

        return array_merge($record, [
            'played'      => $played,
            'avg_for'     => $played ? round($record['gf'] / $played, 1) : 0,
            'avg_against' => $played ? round($record['ga'] / $played, 1) : 0,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────

    /** Normaliza un amistoso a la perspectiva de un club propio. */
    private function perspective(FriendlyMatch $fm, int $myClubId): object
    {
        $side   = $fm->sideForClub($myClubId);
        $result = $fm->resultForClub($myClubId) ?? ['for' => null, 'against' => null, 'outcome' => null];

        return (object) [
            'match'    => $fm,
            'club_id'  => $myClubId,
            'club'     => $side === 'home' ? $fm->homeClub : $fm->awayClub,
            'opponent' => $side === 'home' ? $fm->awayClub : $fm->homeClub,
            'for'      => $result['for'],
            'against'  => $result['against'],
            'outcome'  => $result['outcome'],
            'date'     => $fm->scheduled_at,
        ];
    }

    /** Suma V/E/D y goles a favor/contra de una colección de perspectivas. */
    private function aggregateRecord(Collection $perspectives): array
    {
        $r = ['won' => 0, 'drawn' => 0, 'lost' => 0, 'gf' => 0, 'ga' => 0];

        foreach ($perspectives as $row) {
            $r['gf'] += (int) $row->for;
            $r['ga'] += (int) $row->against;
            match ($row->outcome) {
                'V'     => $r['won']++,
                'E'     => $r['drawn']++,
                'D'     => $r['lost']++,
                default => null,
            };
        }

        return $r;
    }
}
