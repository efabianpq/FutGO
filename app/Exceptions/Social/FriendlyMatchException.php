<?php

namespace App\Exceptions\Social;

use RuntimeException;

/**
 * Error de negocio del ciclo de vida de un amistoso, con mensaje apto para el
 * usuario. El controlador lo captura y lo devuelve como flash `error`.
 */
class FriendlyMatchException extends RuntimeException
{
    public static function make(string $message): self
    {
        return new self($message);
    }
}
