<?php

namespace App\Services\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Social\OpportunityResponse;
use App\Models\Social\ReliabilityScore;
use App\Models\Torneos\Club;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FutGO Social — Fase 3 · Sesión S3-A · Recomendaciones por REGLAS (sin ML).
 *
 * Dos capas de "inteligencia" totalmente determinista y auditable:
 *
 *  1. compatibleRivalsFor() — al publicar un BUSCAR_RIVAL, sugiere hasta 5 clubs
 *     compatibles ANTES de que lleguen respuestas. La compatibilidad son filtros
 *     duros + un score compuesto, no una caja negra:
 *       · misma ciudad (OBLIGATORIO; la ciudad del club = la de su capitán)
 *       · nivel compatible: igual o adyacente (±1 en el ranking de niveles)
 *       · activo recientemente: publicó o respondió una oportunidad ≤30 días
 *       · confiabilidad ≥ MIN_RELIABILITY
 *       · NUNCA un club con disponibilidad pausada por no-shows
 *     Orden = score compuesto (confiabilidad + cercanía de nivel + recencia),
 *     desempate estable por id ascendente.
 *
 *  2. levelRecategorization() — si un club gana consistentemente (≥
 *     RECATEGORIZATION_WINS) contra clubs de nivel SUPERIOR en amistosos con
 *     resultado confirmado, se le sugiere subir su nivel declarado. No fuerza
 *     nada: es un aviso para el capitán, que puede ignorarlo (se persiste en
 *     clubs.level_suggestion_dismissed_at).
 */
class SuggestionService
{
    /** Máximo de clubs sugeridos. */
    public const MAX_SUGGESTIONS = 5;

    /** Ventana (días) para considerar a un club "activo recientemente". */
    public const ACTIVITY_WINDOW_DAYS = 30;

    /** Confiabilidad mínima para que un club entre en las sugerencias. */
    public const MIN_RELIABILITY = 60;

    /** Victorias contra nivel superior que disparan la sugerencia de recategorización. */
    public const RECATEGORIZATION_WINS = 3;

    // Pesos del score compuesto (documentados, no mágicos).
    private const LEVEL_BONUS_EXACT    = 20;
    private const LEVEL_BONUS_ADJACENT = 10;
    private const ACTIVITY_BONUS_7D    = 15;
    private const ACTIVITY_BONUS_30D   = 8;

    // ─────────────────────────────────────────────────────────────────────
    // 1. Clubs compatibles para un BUSCAR_RIVAL
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Sugerencias para una oportunidad BUSCAR_RIVAL ya publicada.
     * Devuelve colección de objetos {club, score, reasons[]}, máx MAX_SUGGESTIONS.
     */
    public function compatibleRivalsFor(Opportunity $opportunity, ?Carbon $now = null): Collection
    {
        // Solo aplica al matching de rival entre clubs.
        if (! $opportunity->isBuscarRival() || ! $opportunity->club_id) {
            return collect();
        }

        return $this->compatibleRivals(
            excludeClubId: $opportunity->club_id,
            city: (string) $opportunity->city,
            level: $opportunity->required_level,
            now: $now,
        );
    }

    /**
     * Núcleo del matching de rivales. Separado para poder usarlo también desde el
     * formulario de publicación (preview) sin una oportunidad persistida.
     */
    public function compatibleRivals(
        int $excludeClubId,
        string $city,
        ?string $level,
        ?Carbon $now = null,
    ): Collection {
        $now  = $now ?? now();
        $city = trim($city);
        if ($city === '') {
            return collect();
        }

        // Niveles compatibles (igual o adyacente). Sin nivel de referencia, no
        // filtramos por nivel (cualquier nivel declarado es candidato).
        $compatibleLevels = $this->compatibleLevels($level);

        // FILTRO DURO en SQL: misma ciudad (vía capitán) + nivel compatible.
        $candidates = Club::query()
            ->where('id', '!=', $excludeClubId)
            ->whereHas('captain', fn ($q) => $q->whereRaw('LOWER(city) = ?', [mb_strtolower($city)]))
            ->when($compatibleLevels !== null, fn ($q) => $q->whereIn('play_level', $compatibleLevels))
            ->get(['id', 'name', 'slug', 'shield_url', 'play_level', 'captain_user_id']);

        if ($candidates->isEmpty()) {
            return collect();
        }

        $ids = $candidates->pluck('id')->all();

        // Confiabilidad cacheada de los candidatos (1 query). Default 100.
        $scores = ReliabilityScore::where('subject_type', 'club')
            ->whereIn('subject_id', $ids)
            ->get()
            ->keyBy('subject_id');

        // Última actividad en oportunidades dentro de la ventana (2 queries).
        $lastActivity = $this->lastActivityMap($ids, $now);

        $windowStart = $now->copy()->subDays(self::ACTIVITY_WINDOW_DAYS);

        $suggestions = $candidates
            ->map(function (Club $club) use ($scores, $lastActivity, $level, $now, $windowStart) {
                $scoreRow    = $scores->get($club->id);
                $reliability = $scoreRow?->score ?? 100;
                $isPaused    = (bool) ($scoreRow?->is_paused ?? false);
                $activeAt    = $lastActivity[$club->id] ?? null;

                return (object) [
                    'club'        => $club,
                    'reliability' => $reliability,
                    'is_paused'   => $isPaused,
                    'active_at'   => $activeAt,
                    'score'       => $this->compositeScore($reliability, $club->play_level, $level, $activeAt, $now),
                    'reasons'     => $this->reasonsFor($club->play_level, $level, $reliability, $activeAt, $now),
                ];
            })
            // FILTROS DUROS de calidad: nunca pausados; activos en la ventana;
            // confiabilidad por encima del umbral.
            ->filter(fn ($s) => ! $s->is_paused)
            ->filter(fn ($s) => $s->active_at !== null && $s->active_at->greaterThanOrEqualTo($windowStart))
            ->filter(fn ($s) => $s->reliability >= self::MIN_RELIABILITY)
            // Orden determinista: score desc, desempate por id asc.
            ->sortBy(fn ($s) => $s->club->id)
            ->sortByDesc(fn ($s) => $s->score)
            ->values()
            ->take(self::MAX_SUGGESTIONS);

        return $suggestions->values();
    }

    /**
     * Niveles compatibles con el de referencia (igual o adyacente, ±1 en el
     * ranking). Null si no hay nivel de referencia (no se filtra por nivel).
     *
     * @return array<int,string>|null
     */
    private function compatibleLevels(?string $level): ?array
    {
        if (! $level) {
            return null;
        }
        $rank = array_search($level, Club::PLAY_LEVELS, true);
        if ($rank === false) {
            return null;
        }

        $levels = [];
        foreach ([$rank - 1, $rank, $rank + 1] as $r) {
            if (isset(Club::PLAY_LEVELS[$r])) {
                $levels[] = Club::PLAY_LEVELS[$r];
            }
        }

        return $levels;
    }

    /**
     * Última fecha de actividad en oportunidades (publicar o responder) por club,
     * dentro de la ventana. Dos queries acotadas, sin N+1.
     *
     * @param  array<int,int>  $clubIds
     * @return array<int,\Illuminate\Support\Carbon>
     */
    private function lastActivityMap(array $clubIds, Carbon $now): array
    {
        $since = $now->copy()->subDays(self::ACTIVITY_WINDOW_DAYS);

        $published = Opportunity::query()
            ->whereIn('club_id', $clubIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('club_id, MAX(created_at) as last_at')
            ->groupBy('club_id')
            ->pluck('last_at', 'club_id');

        $responded = OpportunityResponse::query()
            ->whereIn('club_id', $clubIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('club_id, MAX(created_at) as last_at')
            ->groupBy('club_id')
            ->pluck('last_at', 'club_id');

        $map = [];
        foreach ($clubIds as $id) {
            $dates = array_filter([
                $published[$id] ?? null,
                $responded[$id] ?? null,
            ]);
            if ($dates) {
                $map[$id] = collect($dates)->map(fn ($d) => Carbon::parse($d))->max();
            }
        }

        return $map;
    }

    /** Score compuesto determinista. Confiabilidad base + cercanía de nivel + recencia. */
    private function compositeScore(int $reliability, ?string $candidateLevel, ?string $refLevel, ?Carbon $activeAt, Carbon $now): int
    {
        $score = $reliability;

        // Cercanía de nivel.
        if ($refLevel && $candidateLevel) {
            $diff = abs(
                (int) array_search($candidateLevel, Club::PLAY_LEVELS, true)
                - (int) array_search($refLevel, Club::PLAY_LEVELS, true)
            );
            $score += $diff === 0 ? self::LEVEL_BONUS_EXACT : ($diff === 1 ? self::LEVEL_BONUS_ADJACENT : 0);
        }

        // Recencia de actividad.
        if ($activeAt) {
            $daysAgo = $activeAt->diffInDays($now);
            $score  += $daysAgo <= 7 ? self::ACTIVITY_BONUS_7D : self::ACTIVITY_BONUS_30D;
        }

        return $score;
    }

    /** Razones legibles de por qué se sugiere el club (auditable en la UI). */
    private function reasonsFor(?string $candidateLevel, ?string $refLevel, int $reliability, ?Carbon $activeAt, Carbon $now): array
    {
        $reasons = ['Misma ciudad'];

        if ($refLevel && $candidateLevel) {
            $reasons[] = $candidateLevel === $refLevel ? 'Mismo nivel' : 'Nivel cercano';
        }
        if ($activeAt) {
            $reasons[] = $activeAt->diffInDays($now) <= 7 ? 'Activo esta semana' : 'Activo este mes';
        }
        if ($reliability >= 90) {
            $reasons[] = 'Confiabilidad alta';
        }

        return $reasons;
    }

    // ─────────────────────────────────────────────────────────────────────
    // 2. Sugerencia de recategorización de nivel
    // ─────────────────────────────────────────────────────────────────────

    /**
     * ¿El club debería subir de nivel? Se sugiere cuando ganó ≥
     * RECATEGORIZATION_WINS amistosos confirmados contra clubs de nivel SUPERIOR.
     *
     * Devuelve {current_level, suggested_level, wins} o null si no corresponde
     * (sin nivel declarado, ya en el máximo, sin suficientes victorias, o
     * el capitán ya ignoró la sugerencia).
     */
    public function levelRecategorization(Club $club): ?array
    {
        if ($club->dismissedLevelSuggestion()) {
            return null;
        }

        $rank = $club->playLevelRank();
        if ($rank === null) {
            return null; // sin nivel declarado, no hay base para sugerir
        }
        $maxRank = count(Club::PLAY_LEVELS) - 1;
        if ($rank >= $maxRank) {
            return null; // ya está en el nivel máximo
        }

        $wins = $this->winsAgainstHigherLevel($club, $rank);
        if ($wins < self::RECATEGORIZATION_WINS) {
            return null;
        }

        return [
            'current_level'   => Club::PLAY_LEVELS[$rank],
            'suggested_level' => Club::PLAY_LEVELS[$rank + 1],
            'wins'            => $wins,
        ];
    }

    /**
     * Cuenta victorias en amistosos JUGADOS del club contra rivales cuyo nivel
     * declarado es estrictamente superior al del club.
     */
    private function winsAgainstHigherLevel(Club $club, int $myRank): int
    {
        $matches = FriendlyMatch::jugados()
            ->involvingClub($club->id)
            ->with(['homeClub:id,play_level', 'awayClub:id,play_level'])
            ->get();

        $wins = 0;
        foreach ($matches as $fm) {
            $result = $fm->resultForClub($club->id);
            if (! $result || $result['outcome'] !== 'V') {
                continue;
            }

            $opponent = $fm->home_club_id === $club->id ? $fm->awayClub : $fm->homeClub;
            $oppRank  = $opponent ? array_search($opponent->play_level, Club::PLAY_LEVELS, true) : false;

            if ($oppRank !== false && $oppRank > $myRank) {
                $wins++;
            }
        }

        return $wins;
    }
}
