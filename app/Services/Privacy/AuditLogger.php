<?php

namespace App\Services\Privacy;

use App\Models\Privacy\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Centro de Privacidad · Registro de auditoría (no-bloqueante).
 *
 * Escribe en audit_logs sin tumbar el request si algo falla (atrapa toda
 * excepción, patrón de FeedService::record). NUNCA persiste password/token/
 * document; el email va enmascarado en metadata.
 */
class AuditLogger
{
    /** Claves que jamás deben quedar en metadata (se descartan). */
    private const FORBIDDEN = [
        'password', 'password_confirmation', 'current_password', 'token',
        'remember_token', 'document', 'documento', 'secret', 'api_key',
        'authorization', 'verification_code', 'code',
    ];

    /**
     * Registra una acción. `$user` null → toma el autenticado. `$auditable` es el
     * modelo afectado (opcional). `$meta` se sanea antes de guardar.
     */
    public static function record(string $action, ?User $user = null, ?Model $auditable = null, array $meta = []): void
    {
        try {
            $user ??= auth()->user();
            $request = request();

            AuditLog::create([
                'user_id'        => $user?->id,
                'action'         => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id'   => $auditable?->getKey(),
                'ip'             => $request?->ip(),
                'user_agent'     => $request?->userAgent(),
                'metadata'       => static::sanitize($meta) ?: null,
            ]);
        } catch (\Throwable $e) {
            // Auditar nunca debe romper el flujo del usuario.
            Log::warning('AuditLogger failed', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }

    /** Quita claves prohibidas y enmascara emails. */
    public static function sanitize(array $meta): array
    {
        $clean = [];

        foreach ($meta as $key => $value) {
            if (in_array(mb_strtolower((string) $key), self::FORBIDDEN, true)) {
                continue;
            }

            if (is_string($value) && str_contains($key, 'email')) {
                $value = static::maskEmail($value);
            }

            $clean[$key] = $value;
        }

        return $clean;
    }

    public static function maskEmail(string $email): string
    {
        if (! str_contains($email, '@')) {
            return '***';
        }

        [$local, $domain] = explode('@', $email, 2);
        $first = mb_substr($local, 0, 1);

        return $first.str_repeat('*', max(1, mb_strlen($local) - 1)).'@'.$domain;
    }
}
