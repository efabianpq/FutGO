<?php

namespace App\Services\Social;

use App\Models\Social\FeedEvent;
use App\Models\Social\Follow;
use App\Models\Social\Opportunity;
use App\Models\Torneos\Club;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * FutGO Social — Fase 1 · Sesión S1-E · Feed de eventos del sistema.
 *
 * El Feed NO se publica a mano: agrega eventos que el sistema YA genera
 * (oportunidades, resultados, logros) y los muestra a quien le son relevantes.
 *
 * RELEVANCIA (calculada en lectura, sin filas por usuario):
 *   - Sigue al `actor` o al `subject` del evento (las dos entidades que conecta).
 *   - O el evento se distribuye por su `city` + `required_level` y coincide con
 *     la ciudad/nivel del usuario (nivel nulo = para todos en esa ciudad).
 *
 * RENDIMIENTO: un registro por evento alcanza a todos los relevantes. El contador
 * de no leídos es O(1) en almacenamiento: compara contra `users.feed_last_read_at`.
 *
 * ROBUSTEZ: `record()` NUNCA propaga una excepción — generar un evento de Feed
 * jamás debe romper la acción principal (aceptar oportunidad, cargar resultado).
 */
class FeedService
{
    /** Eventos por página en el Feed. */
    public const PER_PAGE = 15;

    // ─────────────────────────────────────────────────────────────────────
    // Registrar un evento (NO bloqueante)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Persiste un evento de Feed. Si algo falla, lo reporta y devuelve null:
     * la acción que lo originó sigue su curso sin enterarse.
     *
     * @param  array{city?:?string,level?:?string,payload?:array,occurred_at?:\DateTimeInterface}  $opts
     */
    public function record(string $type, ?Model $actor, ?Model $subject, array $opts = []): ?FeedEvent
    {
        try {
            return FeedEvent::create([
                'type'           => $type,
                'actor_type'     => $actor?->getMorphClass(),
                'actor_id'       => $actor?->getKey(),
                'subject_type'   => $subject?->getMorphClass(),
                'subject_id'     => $subject?->getKey(),
                'city'           => $opts['city'] ?? null,
                'required_level' => $opts['level'] ?? null,
                'payload'        => $opts['payload'] ?? [],
                'occurred_at'    => $opts['occurred_at'] ?? now(),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // Leer el Feed
    // ─────────────────────────────────────────────────────────────────────

    /** Eventos relevantes para el usuario, paginados y ordenados por fecha desc. */
    public function feedFor(User $user, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->relevantQuery($user)
            ->with(['actor', 'subject'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * Query base de eventos relevantes para el usuario. El Feed es notificación
     * personal, no un canal de descubrimiento: solo entra lo propio o lo de
     * quien sigues. NO se distribuye por ciudad — eso ya lo cubre la sección
     * Oportunidades (búsqueda/filtro explícito por ciudad). Distribuir eventos
     * por ciudad aquí llenaría el Feed de cosas sin relación con el usuario
     * solo por compartir ubicación. Si no hay identidad propia ni follows,
     * devuelve una query vacía (nada es relevante todavía).
     */
    public function relevantQuery(User $user): Builder
    {
        // "Yo mismo": eventos donde el usuario es directamente el actor/subject
        // (como user) o donde lo es un club que capitanea. Siempre relevante,
        // sin depender de follows — es lo que garantiza que una notificación
        // dirigida (p. ej. "respondieron tu oportunidad") llegue al dueño
        // aunque nadie lo siga.
        $own      = $this->ownedKeys($user);
        $followed = $this->followedKeys($user);

        return FeedEvent::query()->where(function (Builder $q) use ($own, $followed) {
            $matched = false;

            // 1. Soy el actor o el subject (directamente o vía un club que capitaneo).
            foreach ($own as $type => $ids) {
                if ($ids->isEmpty()) {
                    continue;
                }
                $matched = true;
                $q->orWhere(fn (Builder $s) => $s->where('actor_type', $type)->whereIn('actor_id', $ids));
                $q->orWhere(fn (Builder $s) => $s->where('subject_type', $type)->whereIn('subject_id', $ids));
            }

            // 2. Sigo al actor o al subject (por tipo de entidad).
            foreach ($followed as $type => $ids) {
                if ($ids->isEmpty()) {
                    continue;
                }
                $matched = true;
                $q->orWhere(fn (Builder $s) => $s->where('actor_type', $type)->whereIn('actor_id', $ids));
                $q->orWhere(fn (Builder $s) => $s->where('subject_type', $type)->whereIn('subject_id', $ids));
            }

            // Sin identidad propia ni follows: nada es relevante todavía.
            if (! $matched) {
                $q->whereRaw('1 = 0');
            }
        });
    }

    // ─────────────────────────────────────────────────────────────────────
    // Contador de no leídos / marcar leído
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Eventos relevantes posteriores a la última lectura (o al alta del usuario
     * si nunca leyó). Es el badge del navbar.
     */
    public function unreadCount(User $user): int
    {
        $since = $user->feed_last_read_at ?? $user->created_at;

        $query = $this->relevantQuery($user);
        if ($since) {
            $query->where('occurred_at', '>', $since);
        }

        return $query->count();
    }

    /** Marca todo como leído: el contador del navbar vuelve a cero. */
    public function markRead(User $user): void
    {
        $user->forceFill(['feed_last_read_at' => now()])->save();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Contenido de entrada (Feed vacío)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Oportunidades activas de la ciudad del usuario — el "primer contenido" para
     * un usuario nuevo sin follows ni eventos relevantes. Filtra por nivel igual
     * que la exploración pública (nivel nulo o coincidente).
     */
    public function entryOpportunities(User $user, int $limit = 6): Collection
    {
        if (! $user->city) {
            return collect();
        }

        return Opportunity::query()
            ->active()
            ->where('city', 'like', '%' . $user->city . '%')
            ->when($user->play_level, fn ($q) => $q->where(function ($w) use ($user) {
                $w->whereNull('required_level')->orWhere('required_level', $user->play_level);
            }))
            ->with(['user:id,name,futgo_id,avatar_url', 'club:id,name,slug,shield_url'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Identidades que el usuario ES: su propio user id y los clubes que
     * capitanea. Da soporte a notificaciones dirigidas (ver `relevantQuery`).
     *
     * @return array<string,\Illuminate\Support\Collection<int,int>>
     */
    private function ownedKeys(User $user): array
    {
        return [
            'user' => collect([$user->id]),
            'club' => Club::where('captain_user_id', $user->id)->pluck('id'),
        ];
    }

    /**
     * Entidades que el usuario sigue, agrupadas por tipo (morph alias).
     *
     * @return array<string,\Illuminate\Support\Collection<int,int>>
     */
    private function followedKeys(User $user): array
    {
        $rows = Follow::where('user_id', $user->id)->get(['followable_type', 'followable_id']);

        $grouped = [];
        foreach (['club', 'user', 'tournament'] as $type) {
            $grouped[$type] = $rows->where('followable_type', $type)->pluck('followable_id');
        }

        return $grouped;
    }

    /** Última marca de lectura relativa (helper para vistas/tests). */
    public function lastReadAt(User $user): ?Carbon
    {
        return $user->feed_last_read_at;
    }
}
