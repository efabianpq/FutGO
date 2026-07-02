<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Edad mínima de registro
    |--------------------------------------------------------------------------
    | Nadie menor a esta edad puede crear cuenta (Política para menores).
    */
    'min_age' => (int) env('PRIVACY_MIN_AGE', 14),

    /*
    |--------------------------------------------------------------------------
    | Contacto del responsable del tratamiento
    |--------------------------------------------------------------------------
    */
    'contact_email' => env('PRIVACY_CONTACT_EMAIL', 'privacidad@futgo.com.co'),

    /*
    |--------------------------------------------------------------------------
    | Consentimiento parental
    |--------------------------------------------------------------------------
    | Cuando está activo, los menores de 18 requieren el correo y la confirmación
    | de su representante legal antes de operar plenamente. Se puede desactivar
    | para lanzar primero el registro adulto (ver docs/PLAN_CENTRO_PRIVACIDAD.md).
    */
    'parental_consent' => (bool) env('PRIVACY_PARENTAL_CONSENT', true),

    /*
    |--------------------------------------------------------------------------
    | Periodo de gracia para eliminación de cuenta (días)
    |--------------------------------------------------------------------------
    */
    'deletion_grace_days' => (int) env('PRIVACY_DELETION_GRACE_DAYS', 30),

];
