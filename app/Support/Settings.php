<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class Settings
{
    private const PRIZE_POOL_KEY = 'pachon.prize_pool';

    public static function prizePool(): ?int
    {
        $v = Cache::get(self::PRIZE_POOL_KEY);

        return $v === null ? null : (int) $v;
    }

    public static function setPrizePool(int $amount): void
    {
        Cache::forever(self::PRIZE_POOL_KEY, $amount);
    }

    public static function clearPrizePool(): void
    {
        Cache::forget(self::PRIZE_POOL_KEY);
    }

    /**
     * Reparto del pozo: 60% / 20% / 10% / 10% plataforma.
     * Devuelve null si aún no se configuró el pozo total.
     *
     * @return array{first:int|null, second:int|null, third:int|null, platform:int|null, pool:int|null}
     */
    public static function prizeBreakdown(): array
    {
        $pool = self::prizePool();
        if ($pool === null) {
            return [
                'pool' => null,
                'first' => null,
                'second' => null,
                'third' => null,
                'platform' => null,
            ];
        }

        return [
            'pool' => $pool,
            'first' => (int) round($pool * 0.60),
            'second' => (int) round($pool * 0.20),
            'third' => (int) round($pool * 0.10),
            'platform' => (int) round($pool * 0.10),
        ];
    }
}
