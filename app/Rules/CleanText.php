<?php

namespace App\Rules;

use App\Support\Social\ContentFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Regla de validación: el texto no debe contener palabras prohibidas
 * (filtro básico de moderación de FutGO Social). La longitud máxima se valida
 * aparte con la regla `max:` de Laravel para mantener mensajes claros por campo.
 */
class CleanText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ContentFilter::hasBannedWord($value)) {
            $fail('El texto contiene lenguaje no permitido. Reformulalo, por favor.');
        }
    }
}
