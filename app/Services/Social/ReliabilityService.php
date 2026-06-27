<?php

namespace App\Services\Social;

use App\Models\Social\ReliabilityEvent;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * FutGO Social — Fase 1 · Sesión S1-D · Score de Confiabilidad.
 *
 * Calcula y cachea el score 0-100 de confiabilidad para usuarios y clubs.
 * Derivado de `reliability_events`, reconstruible — mismo patrón que
 * FairPlayService. DISTINTO del fair play: mide asistencia/puntualidad,
 * no disciplina en cancha.
 *
 * FÓRMULA: score parte en 100 y acumula delta por cada evento en la ventana
 * de 90 días (WINDOW_DAYS). Los pesos están en WEIGHTS (configurable sin
 * tocar la lógica).
 */
class ReliabilityService
{
    /** Ventana de eventos que influyen en el score (días). */
    public const WINDOW_DAYS = 90;

    /** Ventana de no-shows para la pausa automática (días). */
    public const PAUSE_WINDOW_DAYS = 30;

    /** No-shows en PAUSE_WINDOW_DAYS que activan la pausa. */
    public const PAUSE_THRESHOLD = 2;

    /**
     * Pesos (delta por evento). Positivo → suma, negativo → resta.
     * El score final se clampea a [0, 100].
     */
    public const WEIGHTS = [
        ReliabilityEvent::TYPE_NO_SHOW               => -20,
        ReliabilityEvent::TYPE_CANCELACION_TARDIA    => -10,
        ReliabilityEvent::TYPE_RESPUESTA_RAPIDA      =>   5,
        ReliabilityEvent::TYPE_CALIFICACION_POSITIVA =>   8,
        ReliabilityEvent::TYPE_CALIFICACION_NEGATIVA => -12,
    ];

    // ─────────────────────────────────────────────────────────────────────
    // Recalcular para un usuario
    // ─────────────────────────────────────────────────────────────────────

    public function refreshForUser(User $user): ReliabilityScore
    {
        return $this->refreshForSubject('user', $user->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Recalcular para un club
    // ─────────────────────────────────────────────────────────────────────

    public function refreshForClub(Club $club): ReliabilityScore
    {
        return $this->refreshForSubject('club', $club->id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Reconstruir todos (para el scheduler diario)
    // ─────────────────────────────────────────────────────────────────────

    public function rebuild(): void
    {
        // Todos los usuarios con al menos un evento.
        $userIds = ReliabilityEvent::where('subject_type', 'user')
            ->distinct()
            ->pluck('subject_id');
        foreach (User::whereIn('id', $userIds)->get() as $user) {
            $this->refreshForUser($user);
        }

        // Todos los clubs con al menos un evento.
        $clubIds = ReliabilityEvent::where('subject_type', 'club')
            ->distinct()
            ->pluck('subject_id');
        foreach (Club::whereIn('id', $clubIds)->get() as $club) {
            $this->refreshForClub($club);
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers públicos
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Retorna el score cacheado de un sujeto, o 100 si aún no tiene cache.
     * No dispara recálculo — usar refreshFor* para eso.
     */
    public function scoreFor(string $subjectType, int $subjectId): int
    {
        $row = ReliabilityScore::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->first();

        return $row?->score ?? 100;
    }

    /**
     * ¿El sujeto está pausado (no puede publicar nuevas oportunidades)?
     */
    public function isPaused(string $subjectType, int $subjectId): bool
    {
        $row = ReliabilityScore::where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->first();

        return $row?->is_paused ?? false;
    }

    /**
     * Reactiva manualmente al sujeto: limpia is_paused.
     * No altera el score ni los eventos — solo desbloquea la disponibilidad.
     */
    public function reactivate(string $subjectType, int $subjectId): ReliabilityScore
    {
        $row = ReliabilityScore::firstOrNew(
            ['subject_type' => $subjectType, 'subject_id' => $subjectId]
        );

        $row->is_paused = false;
        $row->paused_at = null;
        $row->save();

        return $row;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Lógica interna
    // ─────────────────────────────────────────────────────────────────────

    private function refreshForSubject(string $type, int $id): ReliabilityScore
    {
        $window = now()->subDays(self::WINDOW_DAYS);

        $events = ReliabilityEvent::where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('occurred_at', '>=', $window)
            ->get();

        // Contadores por tipo en la ventana.
        $noShows          = $events->where('type', ReliabilityEvent::TYPE_NO_SHOW)->count();
        $lateCancellations = $events->where('type', ReliabilityEvent::TYPE_CANCELACION_TARDIA)->count();
        $fastResponses    = $events->where('type', ReliabilityEvent::TYPE_RESPUESTA_RAPIDA)->count();
        $positiveRatings  = $events->where('type', ReliabilityEvent::TYPE_CALIFICACION_POSITIVA)->count();
        $negativeRatings  = $events->where('type', ReliabilityEvent::TYPE_CALIFICACION_NEGATIVA)->count();

        $score = $this->computeScore(
            $noShows,
            $lateCancellations,
            $fastResponses,
            $positiveRatings,
            $negativeRatings,
        );

        // Pausa automática: 2+ no-shows en los últimos 30 días.
        $recentNoShows = ReliabilityEvent::where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('type', ReliabilityEvent::TYPE_NO_SHOW)
            ->where('occurred_at', '>=', now()->subDays(self::PAUSE_WINDOW_DAYS))
            ->count();

        $existingRow = ReliabilityScore::where('subject_type', $type)
            ->where('subject_id', $id)
            ->first();

        $shouldPause = $recentNoShows >= self::PAUSE_THRESHOLD;
        // Si ya estaba pausado manualmente (por admin u otro motivo), lo
        // preservamos. Solo activamos la pausa automática nueva.
        $isPaused  = $shouldPause || ($existingRow?->is_paused ?? false);
        $pausedAt  = $isPaused
            ? ($existingRow?->paused_at ?? ($shouldPause && ! ($existingRow?->is_paused ?? false) ? now() : null))
            : null;

        return ReliabilityScore::updateOrCreate(
            ['subject_type' => $type, 'subject_id' => $id],
            [
                'score'              => $score,
                'no_shows'           => $noShows,
                'late_cancellations' => $lateCancellations,
                'fast_responses'     => $fastResponses,
                'positive_ratings'   => $positiveRatings,
                'negative_ratings'   => $negativeRatings,
                'is_paused'          => $isPaused,
                'paused_at'          => $isPaused ? ($existingRow?->paused_at ?? now()) : null,
                'calculated_at'      => now(),
            ]
        );
    }

    /** Fórmula: arranca en 100, aplica deltas por evento, clampea a [0, 100]. */
    public function computeScore(
        int $noShows,
        int $lateCancellations,
        int $fastResponses,
        int $positiveRatings,
        int $negativeRatings,
    ): int {
        $delta = $noShows           * self::WEIGHTS[ReliabilityEvent::TYPE_NO_SHOW]
               + $lateCancellations * self::WEIGHTS[ReliabilityEvent::TYPE_CANCELACION_TARDIA]
               + $fastResponses     * self::WEIGHTS[ReliabilityEvent::TYPE_RESPUESTA_RAPIDA]
               + $positiveRatings   * self::WEIGHTS[ReliabilityEvent::TYPE_CALIFICACION_POSITIVA]
               + $negativeRatings   * self::WEIGHTS[ReliabilityEvent::TYPE_CALIFICACION_NEGATIVA];

        return max(0, min(100, 100 + $delta));
    }
}
