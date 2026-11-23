<?php

/*
|--------------------------------------------------------------------------
| Trusted Proxies Configuration (PaaS / Reverse Proxy)
|--------------------------------------------------------------------------
|
| En despliegues detrás de proxies inversos (Railway, Render, Fly.io, Nginx),
| TRUSTED_PROXIES permite confiar en cabeceras X-Forwarded-* para detectar
| HTTPS real, host público e IP del cliente. Use '*' para entornos PaaS,
| IPs/CIDRs separados por coma para proxies fijos, o 'none' para desactivar.
|
*/

$raw = env('TRUSTED_PROXIES', '*');

if (is_string($raw)) {
    $trimmed = trim($raw);
    $lower = strtolower($trimmed);

    if (in_array($lower, ['none', 'false', 'off'], true)) {
        $proxies = [];
    } elseif ($trimmed === '' || $trimmed === '*') {
        $proxies = '*';
    } elseif ($trimmed === '**') {
        $proxies = '**';
    } else {
        $proxies = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
    }
} else {
    $proxies = $raw;
}

return [
    'proxies' => $proxies,
];
