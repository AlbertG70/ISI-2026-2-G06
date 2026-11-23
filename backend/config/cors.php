<?php

$rawOrigins = trim((string) env('CORS_ALLOWED_ORIGINS', '*'));

if ($rawOrigins === '' || $rawOrigins === '*') {
    $allowedOrigins = ['*'];
} else {
    $allowedOrigins = array_values(array_filter(array_map(
        static fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', $rawOrigins)
    )));
    if ($allowedOrigins === []) {
        $allowedOrigins = ['*'];
    }
}

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | En producción puede restringir los orígenes permitidos definiendo
    | CORS_ALLOWED_ORIGINS (ej. "https://dagaz.up.railway.app,https://dagaz.co").
    | Por defecto usa '*' para compatibilidad con desarrollo local y apps móviles.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'health', '*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => (int) env('CORS_MAX_AGE', 3600),

    'supports_credentials' => filter_var(env('CORS_SUPPORTS_CREDENTIALS', false), FILTER_VALIDATE_BOOLEAN),

];
