<?php

return [
    'google_ai_key'    => env('GOOGLE_AI_API_KEY'),
    'chat_model'       => env('SUPPORT_CHAT_MODEL', 'gemini-1.5-flash'),
    'max_tokens'       => env('SUPPORT_AI_MAX_TOKENS', 1000),
    'temperature'      => 0.3,
    'team_email'       => env('SUPPORT_TEAM_EMAIL'),
    'escalation_after' => env('SUPPORT_ESCALATION_THRESHOLD', 2),
    'pattern_window'   => env('SUPPORT_PATTERN_WINDOW_MINUTES', 30),
    'pattern_min'      => env('SUPPORT_PATTERN_MIN_TICKETS', 5),

    // Componentes monitoreados (en orden de visualización)
    'monitored_components' => [
        'plataforma', 'login', 'correos',
        'notificaciones', 'ranking', 'scheduler',
    ],

    // Etiquetas para la UI
    'component_labels' => [
        'plataforma'     => 'Plataforma',
        'login'          => 'Inicio de sesión',
        'correos'        => 'Correos',
        'notificaciones' => 'Notificaciones push',
        'ranking'        => 'Ranking',
        'scheduler'      => 'Automatizaciones',
    ],

    // Colores de estado para la UI (clases Tailwind)
    'status_colors' => [
        'operativo'     => 'text-green-600',
        'degradado'     => 'text-yellow-500',
        'caido'         => 'text-red-600',
        'mantenimiento' => 'text-blue-500',
    ],
];
