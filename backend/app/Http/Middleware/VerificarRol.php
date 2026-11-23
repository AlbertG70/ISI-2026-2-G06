<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * VerificarRol — Middleware de Control de Acceso Basado en Roles (RBAC)
 *
 * Restringe el acceso a rutas protegidas según el rol del usuario autenticado:
 *   - 'admin'  → Administrador total
 *   - 'editor' → Revisor y verificador editorial
 *   - 'adulto' → Usuario verificado estándar
 *   - 'lector' → Lector básico
 *
 * Uso en rutas:
 *   Route::middleware(['auth:sanctum', 'rol:admin'])->...
 *   Route::middleware(['auth:sanctum', 'rol:admin,editor'])->...
 */
class VerificarRol
{
    /**
     * Maneja una solicitud entrante y valida los roles permitidos.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$rolesPermitidos
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string ...$rolesPermitidos): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return new JsonResponse([
                'mensaje' => 'No autenticado. Debe proporcionar un token de acceso válido.',
            ], 401);
        }

        // Si el usuario es administrador, tiene acceso irrestricto
        if ($usuario->rol === 'admin') {
            return $next($request);
        }

        // Validar si el rol del usuario coincide con alguno de los permitidos
        if (! in_array($usuario->rol, $rolesPermitidos, true)) {
            return new JsonResponse([
                'mensaje'         => 'Acceso denegado. No posee los permisos requeridos para esta acción.',
                'rol_actual'      => $usuario->rol,
                'roles_requeridos' => $rolesPermitidos,
            ], 403);
        }

        return $next($request);
    }
}
