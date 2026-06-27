<?php

namespace App\Exceptions\Torneos;

use RuntimeException;

/**
 * Error de negocio del flujo de reclamo de perfil, con mensaje apto para el
 * usuario. El controlador lo captura y lo devuelve como flash `error` (no es 500).
 */
class ProfileClaimException extends RuntimeException
{
    public static function make(string $message): self
    {
        return new self($message);
    }
}
