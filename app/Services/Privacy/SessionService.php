<?php

namespace App\Services\Privacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Centro de Privacidad · Sesiones y dispositivos activos.
 *
 * Lee la tabla `sessions` (driver database). Permite al usuario ver desde dónde
 * tiene sesión abierta y cerrarlas remotamente.
 */
class SessionService
{
    /** Sesiones activas del usuario, con la actual marcada. */
    public function forUser(User $user, ?string $currentId = null): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($row) => [
                'id'            => $row->id,
                'is_current'    => $currentId !== null && $row->id === $currentId,
                'ip'            => $row->ip_address,
                'device'        => $this->deviceLabel($row->user_agent),
                'user_agent'    => $row->user_agent,
                'last_activity' => Carbon::createFromTimestamp($row->last_activity),
            ]);
    }

    /** Cierra una sesión puntual del usuario (no puede cerrar la de otro). */
    public function destroy(User $user, string $sessionId): void
    {
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', $sessionId)
            ->delete();
    }

    /** Cierra todas las sesiones del usuario salvo la actual. */
    public function destroyOthers(User $user, string $currentId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $currentId)
            ->delete();
    }

    /** Etiqueta legible del dispositivo/navegador a partir del user-agent. */
    private function deviceLabel(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Dispositivo desconocido';
        }

        $ua = $userAgent;

        $os = match (true) {
            str_contains($ua, 'FutGO-Android'), str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'FutGO-iOS'), str_contains($ua, 'iPhone'), str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Windows')   => 'Windows',
            str_contains($ua, 'Macintosh'), str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux')     => 'Linux',
            default => 'Otro',
        };

        $browser = match (true) {
            str_contains($ua, 'FutGO-')  => 'App FutGO',
            str_contains($ua, 'Edg')     => 'Edge',
            str_contains($ua, 'Chrome')  => 'Chrome',
            str_contains($ua, 'Firefox') => 'Firefox',
            str_contains($ua, 'Safari')  => 'Safari',
            default => 'Navegador',
        };

        return "{$browser} · {$os}";
    }
}
