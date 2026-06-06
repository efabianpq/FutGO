<?php

namespace App\Services\Torneos;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;

/**
 * Credencial digital antifraude (Sesión D).
 *
 * Responsabilidades:
 *  - Generar el identificador FUTGO único (FG-XXXXXX) del jugador.
 *  - Firmar/verificar el identificador (HMAC) para probar que el QR lo emitió FUTGO.
 *  - Construir la URL que viaja en el QR (solo identificador público + firma) y
 *    renderizar el QR como SVG en PHP puro (sin GD/imagick → portable a Hostinger).
 *
 * PRIVACIDAD: el QR/URL NUNCA contienen nombre, email ni documento. Solo el
 * identificador público (futgo_id), que por sí mismo no es dato sensible, más una
 * firma que se valida contra el backend.
 */
class CredentialService
{
    /** Alfabeto sin caracteres ambiguos (sin 0/O/1/I/L/U). */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTVWXYZ';

    /** Largo de la firma HMAC truncada que viaja en la URL. */
    private const SIG_LENGTH = 16;

    /** Genera un identificador FG-XXXXXX que no exista todavía en users. */
    public static function nextFutgoId(): string
    {
        do {
            $id = self::randomId();
        } while (DB::table('users')->where('futgo_id', $id)->exists());

        return $id;
    }

    private static function randomId(): string
    {
        $body = '';
        $max = strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < 6; $i++) {
            $body .= self::ALPHABET[random_int(0, $max)];
        }

        return 'FG-' . $body;
    }

    /** Firma HMAC del identificador (prueba de emisión por FUTGO). */
    public static function signatureFor(string $futgoId): string
    {
        return substr(hash_hmac('sha256', $futgoId, self::secret()), 0, self::SIG_LENGTH);
    }

    /** Verifica la firma en tiempo constante. Sin firma => no verificable. */
    public static function verify(string $futgoId, ?string $sig): bool
    {
        if (! $sig) {
            return false;
        }

        return hash_equals(self::signatureFor($futgoId), $sig);
    }

    /** Secreto derivado del APP_KEY (no se expone el key directamente). */
    private static function secret(): string
    {
        return hash('sha256', 'futgo-credential|' . config('app.key'));
    }

    /** URL absoluta codificada en el QR: identificador público + firma. */
    public static function qrUrlFor(User $user): string
    {
        return route('torneos.validar', [
            'fg'  => $user->futgo_id,
            'sig' => self::signatureFor($user->futgo_id),
        ]);
    }

    /** QR como SVG inline (PHP puro, sin dependencias de extensión binaria). */
    public static function qrSvgFor(User $user, int $size = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($size), new SvgImageBackEnd()));

        return $writer->writeString(self::qrUrlFor($user));
    }
}
