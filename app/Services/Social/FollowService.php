<?php

namespace App\Services\Social;

use App\Models\Social\Follow;
use App\Models\Torneos\Club;
use App\Models\Torneos\Tournament;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * FutGO Social — Fase 1 · Sesión S1-E · seguir / dejar de seguir entidades.
 *
 * Acción TOGGLE y libre: no hay aprobación ni notificación al seguido (para no
 * generar spam en esta fase). Un usuario sigue clubs, jugadores (otros users) y
 * torneos. El par (seguidor, seguido) es único — garantizado por la tabla y por
 * `firstOrCreate` acá.
 */
class FollowService
{
    /** Tipos de entidad seguibles (morph alias). */
    public const FOLLOWABLE = [
        'club'       => Club::class,
        'user'       => User::class,
        'tournament' => Tournament::class,
    ];

    /**
     * Alterna el seguimiento: si no lo sigue lo empieza a seguir, si ya lo sigue
     * lo deja. Devuelve el estado final (`true` = ahora lo sigue).
     */
    public function toggle(User $follower, Model $followable): bool
    {
        $this->assertFollowable($follower, $followable);

        $existing = Follow::where('user_id', $follower->id)
            ->where('followable_type', $followable->getMorphClass())
            ->where('followable_id', $followable->getKey())
            ->first();

        if ($existing) {
            $existing->delete();

            return false;
        }

        Follow::create([
            'user_id'         => $follower->id,
            'followable_type' => $followable->getMorphClass(),
            'followable_id'   => $followable->getKey(),
        ]);

        return true;
    }

    /** ¿`$follower` ya sigue a `$followable`? */
    public function isFollowing(User $follower, Model $followable): bool
    {
        return Follow::where('user_id', $follower->id)
            ->where('followable_type', $followable->getMorphClass())
            ->where('followable_id', $followable->getKey())
            ->exists();
    }

    /** Cantidad de seguidores de una entidad. */
    public function followerCount(Model $followable): int
    {
        return Follow::where('followable_type', $followable->getMorphClass())
            ->where('followable_id', $followable->getKey())
            ->count();
    }

    /**
     * Valida que la entidad sea de un tipo seguible y que el usuario no se siga a
     * sí mismo.
     */
    private function assertFollowable(User $follower, Model $followable): void
    {
        $type = $followable->getMorphClass();

        abort_unless(array_key_exists($type, self::FOLLOWABLE), 422, 'No se puede seguir esta entidad.');

        // Un jugador no se sigue a sí mismo.
        if ($type === 'user' && (int) $followable->getKey() === (int) $follower->id) {
            abort(422, 'No podés seguirte a vos mismo.');
        }
    }
}
