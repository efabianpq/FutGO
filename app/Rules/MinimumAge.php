<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * Centro de Privacidad · Valida la edad mínima de registro (Política para menores).
 *
 * Se aplica sobre una fecha de nacimiento (Y-m-d). Rechaza fechas futuras,
 * inválidas o de personas menores a la edad mínima configurada.
 */
class MinimumAge implements ValidationRule
{
    public function __construct(private ?int $minAge = null)
    {
        $this->minAge ??= (int) config('privacy.min_age', 14);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $birthdate = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            $fail('La fecha de nacimiento no es válida.');

            return;
        }

        if ($birthdate->isFuture()) {
            $fail('La fecha de nacimiento no puede ser futura.');

            return;
        }

        if ($birthdate->age < $this->minAge) {
            $fail("Debes tener al menos {$this->minAge} años para registrarte.");
        }
    }
}
