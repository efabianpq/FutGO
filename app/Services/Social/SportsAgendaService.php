<?php

namespace App\Services\Social;

use App\Models\Social\FriendlyMatch;
use App\Models\Social\Opportunity;
use App\Models\Torneos\MatchCallUp;
use App\Models\Torneos\TeamPlayer;
use App\Models\Torneos\TournamentMatch;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FutGO Social — Fase 2 · Sesión S2-A · Agenda deportiva unificada.
 *
 * Vista de LECTURA: no inventa datos, solo agrega lo que el usuario ya tiene
 * programado o pendiente, desde las fuentes que ya existen:
 *
 *  - Partidos de torneo de sus equipos (programados o en vivo) + su convocatoria.
 *  - Amistosos confirmados de sus clubs (con recordatorio de carga si ya pasaron).
 *  - Convocatorias pendientes de respuesta (acción confirmar/declinar inline).
 *  - Oportunidades propias próximas a vencer.
 *
 * Excluye lo cancelado: torneos `cancelled` y amistosos `cancelado` no aparecen.
 * Cada ítem es un objeto homogéneo, y la colección sale ordenada cronológicamente
 * (los ítems sin fecha van al final).
 */
class SportsAgendaService
{
    /** Tipos de ítem. */
    public const KIND_TOURNAMENT_MATCH = 'tournament_match';
    public const KIND_FRIENDLY          = 'friendly';
    public const KIND_OPPORTUNITY       = 'opportunity';

    /** Ventana (días) para considerar una oportunidad "próxima a vencer". */
    public const EXPIRING_WINDOW_DAYS = 7;

    public function __construct(private FriendlyMatchService $friendlies) {}

    /**
     * Agenda completa del usuario, ordenada cronológicamente (fecha asc; los
     * ítems sin fecha al final). Sin N+1: cada fuente es una query con eager
     * loading de las relaciones que la vista necesita.
     */
    public function for(User $user, ?Carbon $now = null): Collection
    {
        $now = $now ?? now();

        $items = collect()
            ->merge($this->tournamentItems($user))
            ->merge($this->friendlyItems($user, $now))
            ->merge($this->opportunityItems($user, $now));

        // Orden cronológico: con fecha primero (asc), sin fecha al final.
        return $items
            ->sortBy(fn ($item) => $item->date?->getTimestamp() ?? PHP_INT_MAX)
            ->values();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Fuentes
    // ─────────────────────────────────────────────────────────────────────

    /** Partidos de torneo (programados/en vivo) de los equipos del usuario. */
    private function tournamentItems(User $user): Collection
    {
        $teamPlayers = TeamPlayer::where('user_id', $user->id)->get(['id', 'team_id']);
        $teamIds     = $teamPlayers->pluck('team_id')->unique()->values();
        $tpIds       = $teamPlayers->pluck('id');

        if ($teamIds->isEmpty()) {
            return collect();
        }

        $matches = TournamentMatch::query()
            ->where(fn ($q) => $q->whereIn('home_team_id', $teamIds)->orWhereIn('away_team_id', $teamIds))
            ->whereIn('status', ['scheduled', 'live'])
            ->with(['homeTeam:id,name', 'awayTeam:id,name', 'phase:id,tournament_id', 'phase.tournament:id,name,slug,status'])
            ->get();

        // Convocatorias del usuario para esos partidos, indexadas por partido.
        $callUps = MatchCallUp::whereIn('match_id', $matches->pluck('id'))
            ->whereIn('team_player_id', $tpIds)
            ->get()
            ->keyBy('match_id');

        return $matches
            // Excluye partidos de torneos cancelados.
            ->filter(fn ($m) => ($m->phase?->tournament?->status ?? null) !== 'cancelled')
            ->map(function ($m) use ($callUps) {
                $callUp        = $callUps->get($m->id);
                $needsResponse = $callUp !== null && $callUp->status === 'convocado';

                return (object) [
                    'kind'        => self::KIND_TOURNAMENT_MATCH,
                    'date'        => $m->scheduled_at,
                    'title'       => ($m->homeTeam?->name ?? 'Por definir') . ' vs ' . ($m->awayTeam?->name ?? 'Por definir'),
                    'subtitle'    => $m->phase?->tournament?->name,
                    'status'      => $needsResponse ? 'convocatoria_pendiente' : ($callUp?->status ?? 'sin_convocatoria'),
                    'action'      => $needsResponse ? 'respond_callup' : null,
                    'tournament'  => $m->phase?->tournament,
                    'match'       => $m,
                    'callUp'      => $callUp,
                ];
            })
            ->values();
    }

    /** Amistosos confirmados de los clubs del usuario. */
    private function friendlyItems(User $user, Carbon $now): Collection
    {
        $clubIds = $this->friendlies->userClubIds($user);
        if (empty($clubIds)) {
            return collect();
        }

        return FriendlyMatch::query()
            ->confirmados() // excluye cancelados, jugados y en disputa
            ->involvingAnyClub($clubIds)
            ->with(['homeClub:id,name,slug', 'awayClub:id,name,slug'])
            ->get()
            ->map(function ($fm) use ($now) {
                // Confirmado y con fecha pasada → recordatorio de cargar resultado.
                $needsResult = $fm->scheduled_at !== null && $fm->scheduled_at->lessThan($now);

                return (object) [
                    'kind'     => self::KIND_FRIENDLY,
                    'date'     => $fm->scheduled_at,
                    'title'    => ($fm->homeClub?->name ?? '—') . ' vs ' . ($fm->awayClub?->name ?? '—'),
                    'subtitle' => $fm->location ?: 'Amistoso',
                    'status'   => $needsResult ? 'resultado_pendiente' : 'confirmado',
                    'action'   => $needsResult ? 'report_friendly' : null,
                    'friendly' => $fm,
                ];
            })
            ->values();
    }

    /** Oportunidades propias próximas a vencer. */
    private function opportunityItems(User $user, Carbon $now): Collection
    {
        $clubIds = $this->friendlies->userClubIds($user);
        $limit   = $now->copy()->addDays(self::EXPIRING_WINDOW_DAYS);

        return Opportunity::query()
            ->where(fn ($q) => $q->where('user_id', $user->id)->orWhereIn('club_id', $clubIds))
            ->whereIn('status', [Opportunity::STATUS_ABIERTA, Opportunity::STATUS_EN_NEGOCIACION])
            ->with(['club:id,name,slug'])
            ->get()
            ->map(function ($op) {
                // Fecha de vigencia efectiva: igual criterio que el scope active().
                $deadline = $op->expires_at ?? $op->window_end ?? $op->window_start;

                return (object) [
                    'kind'        => self::KIND_OPPORTUNITY,
                    'date'        => $deadline,
                    'title'       => $op->typeLabel() . ($op->club ? ' · ' . $op->club->name : ''),
                    'subtitle'    => $op->city,
                    'status'      => 'vence_pronto',
                    'action'      => null,
                    'opportunity' => $op,
                    'deadline'    => $deadline,
                ];
            })
            // Solo las que vencen dentro de la ventana (descarta sin fecha y lejanas).
            ->filter(fn ($item) => $item->deadline !== null
                && $item->deadline->greaterThanOrEqualTo($now)
                && $item->deadline->lessThanOrEqualTo($limit))
            ->values();
    }
}
