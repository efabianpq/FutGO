<?php

namespace App\Support;

use App\Models\Setting;

class Settings
{
    public const PRIZE_POOL = 'prize_pool';
    public const TOURNAMENT_NAME = 'tournament_name';
    public const WELCOME_MESSAGE = 'welcome_message';

    public const DEFAULT_TOURNAMENT_NAME = '@SoyPachonMundial';
    public const DEFAULT_WELCOME = 'Pronostica los 104 partidos del Mundial 2026, compite con tus amigos y gana premios reales.';

    public static function get(string $key, ?string $default = null): ?string
    {
        $row = Setting::where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
    }

    public static function prizePool(): ?int
    {
        $v = self::get(self::PRIZE_POOL);
        return ($v === null || $v === '') ? null : (int) $v;
    }

    public static function setPrizePool(int $amount): void
    {
        self::set(self::PRIZE_POOL, (string) $amount);
    }

    public static function clearPrizePool(): void
    {
        self::forget(self::PRIZE_POOL);
    }

    public static function tournamentName(): string
    {
        return self::get(self::TOURNAMENT_NAME, self::DEFAULT_TOURNAMENT_NAME) ?? self::DEFAULT_TOURNAMENT_NAME;
    }

    public static function welcomeMessage(): string
    {
        return self::get(self::WELCOME_MESSAGE, self::DEFAULT_WELCOME) ?? self::DEFAULT_WELCOME;
    }

    /**
     * @return array{pool:int|null, first:int|null, second:int|null, third:int|null, platform:int|null}
     */
    public static function prizeBreakdown(): array
    {
        $pool = self::prizePool();
        if ($pool === null) {
            return ['pool' => null, 'first' => null, 'second' => null, 'third' => null, 'platform' => null];
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
