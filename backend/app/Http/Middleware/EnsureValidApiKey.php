<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\PartnerApiService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureValidApiKey — Middleware de Autenticación M2M para Redacciones Socias
 *
 * TSK-4.2 (Sprint 4 / REQ-16): Protege los endpoints públicos REST de DAGAZ
 * mediante la validación estricta de la cabecera X-API-KEY.
 *
 * @package App\Http\Middleware
 */
class EnsureValidApiKey
{
    public function __construct(
        private readonly PartnerApiService $partnerService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $permisoRequerido  Permiso opcional exigido para la ruta
     */
    public function handle(Request $request, Closure $next, ?string $permisoRequerido = null): Response
    {
        $apiKey = $this->extraerApiKey($request);

        if (empty($apiKey)) {
            return new JsonResponse([
                'error'   => 'unauthorized',
                'mensaje' => 'API Key requerida. Proporcione la credencial en la cabecera X-API-KEY.',
            ], 401);
        }

        if (! $this->partnerService->esKeyValida($apiKey)) {
            return new JsonResponse([
                'error'   => 'forbidden',
                'mensaje' => 'API Key inválida, revocada o no autorizada.',
            ], 403);
        }

        if ($permisoRequerido && ! $this->partnerService->tienePermiso($apiKey, $permisoRequerido)) {
            return new JsonResponse([
                'error'   => 'forbidden',
                'mensaje' => "Su suscripción no posee el permiso '{$permisoRequerido}' requerido para esta operación.",
            ], 403);
        }

        $socio = $this->partnerService->obtenerSocio($apiKey);
        $request->attributes->set('socio', $socio);
        $request->attributes->set('api_key', $apiKey);

        /** @var Response $response */
        $response = $next($request);

        // Añadir cabeceras de trazabilidad del socio en la respuesta
        if ($socio) {
            $response->headers->set('X-Partner-Id', $socio['id_socio']);
            $response->headers->set('X-Partner-Name', $socio['nombre']);
        }

        return $response;
    }

    /**
     * Extrae la API Key del request soportando múltiples esquemas estándar.
     */
    private function extraerApiKey(Request $request): ?string
    {
        // 1. Cabecera directa X-API-KEY (convención principal)
        if ($request->hasHeader('X-API-KEY')) {
            return trim((string) $request->header('X-API-KEY'));
        }

        // 2. Cabecera Authorization: Api-Key <token> o ApiKey <token>
        $auth = $request->header('Authorization');
        if ($auth && preg_match('/^(?:Api-Key|ApiKey|Key)\s+(.+)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }

        // 3. Fallback seguro por parámetro query en peticiones GET
        if ($request->isMethod('GET') && $request->has('api_key')) {
            return trim((string) $request->query('api_key'));
        }

        return null;
    }
}
