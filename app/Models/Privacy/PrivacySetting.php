<?php

namespace App\Models\Privacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Centro de Privacidad · Configuración de privacidad del perfil (1:1 con user).
 *
 * El usuario decide qué se muestra públicamente. Defaults conservadores para
 * datos de contacto; datos deportivos visibles (ver migración).
 */
class PrivacySetting extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    public const ALLOW_MESSAGES = ['nadie', 'companeros', 'todos'];

    protected $fillable = [
        'user_id',
        'show_email',
        'show_phone',
        'show_birthdate',
        'show_city',
        'show_photo',
        'show_stats',
        'show_teams',
        'show_history',
        'public_profile',
        'searchable',
        'indexable_by_search_engines',
        'allow_messages',
    ];

    protected function casts(): array
    {
        return [
            'show_email'                   => 'boolean',
            'show_phone'                   => 'boolean',
            'show_birthdate'               => 'boolean',
            'show_city'                    => 'boolean',
            'show_photo'                   => 'boolean',
            'show_stats'                   => 'boolean',
            'show_teams'                   => 'boolean',
            'show_history'                 => 'boolean',
            'public_profile'               => 'boolean',
            'searchable'                   => 'boolean',
            'indexable_by_search_engines'  => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Valores por defecto conservadores usados al crear la fila en el registro. */
    public static function defaults(): array
    {
        return [
            'show_email'                   => false,
            'show_phone'                   => false,
            'show_birthdate'               => false,
            'show_city'                    => true,
            'show_photo'                   => true,
            'show_stats'                   => true,
            'show_teams'                   => true,
            'show_history'                 => true,
            'public_profile'               => true,
            'searchable'                   => true,
            'indexable_by_search_engines'  => true,
            'allow_messages'               => 'companeros',
        ];
    }
}
