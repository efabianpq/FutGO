<?php

namespace App\Exceptions\Social;

use RuntimeException;

/**
 * Error de negocio del flujo de oportunidades, con mensaje apto para el usuario.
 * El controlador lo captura y lo devuelve como flash `error` (no es un 500).
 */
class OpportunityException extends RuntimeException
{
    public static function make(string $message): self
    {
        return new self($message);
    }

    /** Excepción tipada para disponibilidad pausada por no-shows. */
    public static function paused(): self
    {
        return (new self('Tu disponibilidad está pausada por no-shows acumulados. Reactivala antes de publicar.'))
            ->withPaused();
    }

    /** ¿Es un error de pausa por no-shows? (el controlador puede renderizar UI especial). */
    public bool $isPaused = false;

    private function withPaused(): self
    {
        $this->isPaused = true;

        return $this;
    }

    /** Excepción tipada para suspensión por moderación. */
    public static function suspended(): self
    {
        return (new self('Tu cuenta está suspendida y no podés realizar esta acción.'))
            ->withSuspended();
    }

    public bool $isSuspended = false;

    private function withSuspended(): self
    {
        $this->isSuspended = true;

        return $this;
    }
}
