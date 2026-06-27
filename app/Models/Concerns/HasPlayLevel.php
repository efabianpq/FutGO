<?php

namespace App\Models\Concerns;

/**
 * FutGO Social — nivel de juego declarado (requisito de matching).
 *
 * Compartido por User (jugador) y Club (equipo). El nivel se guarda en la
 * columna `play_level` y es OBLIGATORIO para participar en oportunidades:
 * helpers semánticos + scope para filtrar por nivel en el matching.
 */
trait HasPlayLevel
{
    /** Niveles válidos, de menor a mayor. */
    public const PLAY_LEVELS = ['recreativo', 'intermedio', 'competitivo', 'elite_amateur'];

    /** ¿Declaró un nivel? Sin esto no puede entrar al matching. */
    public function hasPlayLevel(): bool
    {
        return in_array($this->play_level, self::PLAY_LEVELS, true);
    }

    public function isRecreativo(): bool
    {
        return $this->play_level === 'recreativo';
    }

    public function isIntermedio(): bool
    {
        return $this->play_level === 'intermedio';
    }

    public function isCompetitivo(): bool
    {
        return $this->play_level === 'competitivo';
    }

    public function isEliteAmateur(): bool
    {
        return $this->play_level === 'elite_amateur';
    }

    /** Posición numérica del nivel (0..3) o null si no declarado. */
    public function playLevelRank(): ?int
    {
        $i = array_search($this->play_level, self::PLAY_LEVELS, true);

        return $i === false ? null : $i;
    }

    /** Filtra por nivel declarado (usado por el matching de oportunidades). */
    public function scopeWithPlayLevel($query, string $level)
    {
        return $query->where('play_level', $level);
    }

    /** Solo registros con algún nivel declarado (elegibles para matching). */
    public function scopeWithDeclaredLevel($query)
    {
        return $query->whereIn('play_level', self::PLAY_LEVELS);
    }
}
