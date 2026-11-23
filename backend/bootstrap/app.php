<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| DAGAZ — Bootstrap de la Aplicación Laravel
|--------------------------------------------------------------------------
|
| Configuración de la aplicación DAGAZ con API routes, Sanctum y
| manejo de excepciones JSON-first para el backend REST.
|
*/

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Confiar en cabeceras X-Forwarded-* de proxies inversos (Railway, Render, Nginx).
        // La lista de proxies confiables se lee de config/trustedproxy.php (TRUSTED_PROXIES).
        $middleware->trustProxies(
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_PREFIX |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'ability'   => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'rol'       => \App\Http\Middleware\VerificarRol::class,
            'api.key'   => \App\Http\Middleware\EnsureValidApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Retornar JSON para todas las excepciones en rutas de API
        $exceptions->shouldRenderJsonWhen(function ($request, $e) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
