<?php

namespace App\Services\Torneos;

use App\Models\Torneos\FairPlayScore;
use App\Models\Torneos\FutgoRanking;
use Illuminate\Support\Facades\DB;

/**
 * Ranking FUTGO global (Sesión F) — reputación acumulada.
 *
 * FÓRMULA de puntaje (jugador y equipo):
 *   score = goles·4 + asistencias·2 + mvps·6 + victorias·3
 *         + vallas_invictas·2 + partidos·1 + fair_play·0.5
 *
 * Pondera producción (goles/asistencias/MVP), éxito (victorias), constancia
 * (partidos), solidez (vallas) y reputación (fair play). El fair play usado es el
 * GLOBAL del sujeto (la reputación no cambia por ciudad/categoría).
 *
 * Se cachea en futgo_rankings; NO se calcula por request. rebuild() reconstruye
 * todos los alcances: jugadores y equipos × {global, por ciudad, por categoría}.
 */
class RankingService
{
    public const W_GOAL        = 4;
    public const W_ASSIST      = 2;
    public const W_MVP         = 6;
    public const W_WIN         = 3;
    public const W_CLEAN_SHEET = 2;
    public const W_MATCH       = 1;
    public const W_FAIR_PLAY   = 0.5;

    /** Reconstruye toda la tabla de ranking cacheada. */
    public function rebuild(): void
    {
        DB::transaction(function () {
            FutgoRanking::query()->delete();

            // Global.
            $this->buildPlayers('global', null);
            $this->buildTeams('global', null);

            // Por ciudad.
            foreach ($this->distinctValues('city') as $city) {
                $this->buildPlayers('city', $city);
                $this->buildTeams('city', $city);
            }

            // Por categoría.
            foreach ($this->distinctValues('category') as $cat) {
                $this->buildPlayers('category', $cat);
                $this->buildTeams('category', $cat);
            }
        });
    }

    // ── Jugadores ─────────────────────────────────────────────────────────

    private function buildPlayers(string $scopeType, ?string $scopeValue): void
    {
        $rows = DB::table('player_stats as ps')
            ->join('team_players as tp', 'tp.id', '=', 'ps.team_player_id')
            ->join('tournaments as t', 't.id', '=', 'ps.tournament_id')
            ->whereNotNull('tp.user_id')
            ->when($scopeType === 'city', fn ($q) => $q->where('t.city', $scopeValue))
            ->when($scopeType === 'category', fn ($q) => $q->where('t.category', $scopeValue))
            ->groupBy('tp.user_id')
            ->selectRaw('tp.user_id as subject_id,
                COALESCE(SUM(ps.goals),0) goals,
                COALESCE(SUM(ps.assists),0) assists,
                COALESCE(SUM(ps.mvps),0) mvps,
                COALESCE(SUM(ps.wins),0) wins,
                COALESCE(SUM(ps.clean_sheets),0) clean_sheets,
                COALESCE(SUM(ps.matches_played),0) matches_played')
            ->get();

        $fairPlay = $this->fairPlayMap('player');
        $names = DB::table('users')->pluck('name', 'id');

        $this->persist('player', $scopeType, $scopeValue, $rows, $fairPlay, $names);
    }

    // ── Equipos (clubs) ─────────────────────────────────────────────────────

    private function buildTeams(string $scopeType, ?string $scopeValue): void
    {
        $rows = DB::table('player_stats as ps')
            ->join('team_players as tp', 'tp.id', '=', 'ps.team_player_id')
            ->join('teams as te', 'te.id', '=', 'tp.team_id')
            ->join('tournaments as t', 't.id', '=', 'ps.tournament_id')
            ->whereNotNull('te.club_id')
            ->when($scopeType === 'city', fn ($q) => $q->where('t.city', $scopeValue))
            ->when($scopeType === 'category', fn ($q) => $q->where('t.category', $scopeValue))
            ->groupBy('te.club_id')
            ->selectRaw('te.club_id as subject_id,
                COALESCE(SUM(ps.goals),0) goals,
                COALESCE(SUM(ps.assists),0) assists,
                COALESCE(SUM(ps.mvps),0) mvps,
                COALESCE(SUM(ps.wins),0) wins,
                COALESCE(SUM(ps.clean_sheets),0) clean_sheets,
                COALESCE(SUM(ps.matches_played),0) matches_played')
            ->get();

        $fairPlay = $this->fairPlayMap('team');
        $names = DB::table('clubs')->pluck('name', 'id');

        $this->persist('team', $scopeType, $scopeValue, $rows, $fairPlay, $names);
    }

    // ── Persistencia + posiciones ───────────────────────────────────────────

    private function persist(string $subjectType, string $scopeType, ?string $scopeValue, $rows, $fairPlay, $names): void
    {
        $computed = $rows->map(function ($r) use ($fairPlay) {
            $fp = (int) ($fairPlay[$r->subject_id] ?? 100);

            return [
                'subject_id'     => (int) $r->subject_id,
                'goals'          => (int) $r->goals,
                'assists'        => (int) $r->assists,
                'mvps'           => (int) $r->mvps,
                'wins'           => (int) $r->wins,
                'clean_sheets'   => (int) $r->clean_sheets,
                'matches_played' => (int) $r->matches_played,
                'fair_play'      => $fp,
                'score'          => $this->score($r, $fp),
            ];
        })
        // Orden determinista: score, goles, partidos y, como desempate final, subject_id.
        ->sortBy([
            ['score', 'desc'], ['goals', 'desc'], ['matches_played', 'desc'], ['subject_id', 'asc'],
        ])->values();

        $now = now();
        foreach ($computed as $i => $row) {
            FutgoRanking::create([
                'subject_type'   => $subjectType,
                'subject_id'     => $row['subject_id'],
                'scope_type'     => $scopeType,
                'scope_value'    => $scopeType === 'global' ? null : $scopeValue,
                'display_name'   => $names[$row['subject_id']] ?? '—',
                'score'          => $row['score'],
                'matches_played' => $row['matches_played'],
                'goals'          => $row['goals'],
                'assists'        => $row['assists'],
                'mvps'           => $row['mvps'],
                'fair_play'      => $row['fair_play'],
                'position'       => $i + 1,
                'calculated_at'  => $now,
            ]);
        }
    }

    /** Aplica la fórmula ponderada. */
    public function score(object $r, int $fairPlay): int
    {
        return (int) round(
            $r->goals * self::W_GOAL
            + $r->assists * self::W_ASSIST
            + $r->mvps * self::W_MVP
            + $r->wins * self::W_WIN
            + $r->clean_sheets * self::W_CLEAN_SHEET
            + $r->matches_played * self::W_MATCH
            + $fairPlay * self::W_FAIR_PLAY
        );
    }

    /** Mapa subject_id => score de fair play para el tipo dado. */
    private function fairPlayMap(string $subjectType): array
    {
        return FairPlayScore::where('subject_type', $subjectType)
            ->pluck('score', 'subject_id')->all();
    }

    /** Valores distintos no nulos de una columna de tournaments (city/category). */
    private function distinctValues(string $column): array
    {
        return DB::table('tournaments')
            ->whereNotNull($column)
            ->distinct()
            ->pluck($column)
            ->all();
    }
}
