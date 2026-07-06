<?php

// Configuración de CORS. FutGO se sirve como app web tradicional (Blade),
// pero el wrapper Capacitor (WebView nativa) carga el sitio desde un origen
// distinto (capacitor://localhost / http://localhost), por lo que las
// peticiones fetch/XHR con cookies de sesión necesitan CORS explícito.

return [

    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://futgo.com.co',
        'capacitor://localhost',
        'http://localhost',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
