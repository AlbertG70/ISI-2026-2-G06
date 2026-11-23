<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\VerificacionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| DAGAZ — Rutas de la API REST v1
|--------------------------------------------------------------------------
|
| Estrategia Backend-First: todos los endpoints del sistema DAGAZ,
| agrupados por versión bajo el prefijo /api/v1.
|
| Autenticación: Laravel Sanctum (token Bearer).
| Documentación: OpenAPI 3.1 — ver /storage/api-docs/openapi.yaml
|
| Referencia de Casos de Uso:
|   CU05 → Auth endpoints (login, register, logout, perfil)
|   CU06 → Verificar contenidos y obtener resultados
|   CU03 → Guardar contenidos en historial
|   CU04 → Consultar historial del usuario
|   CU01 → Agregar notas complementarias
|   CU02 → Calificar notas comunitarias
|   CU07 → Gestionar fuentes del grafo de credibilidad
|
*/

// ============================================================================
// API v1 — DAGAZ
// ============================================================================
Route::prefix('v1')->group(function () {

    // ──────────────────────────────────────────────────────────────────────────
    // CU05: Autenticación y Credenciales (sin token requerido)
    // ──────────────────────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {

        /**
         * POST /api/v1/auth/register
         * Registrar nuevo usuario (adulto o editor).
         */
        Route::post('register', [AuthController::class, 'register'])
            ->name('auth.register');

        /**
         * POST /api/v1/auth/login
         * Iniciar sesión y obtener token Bearer.
         * CU05 — Flujo Básico: validarCredenciales()
         */
        Route::post('login', [AuthController::class, 'login'])
            ->name('auth.login');

    });

    // ──────────────────────────────────────────────────────────────────────────
    // CU06 & CU07: Verificación de Contenidos y Fuentes (Público / Abierto)
    // ──────────────────────────────────────────────────────────────────────────
    /**
     * POST /api/v1/verificacion
     * Verificar contenido multicanal (TEXTO, URL, IMAGEN_OCR, RED_SOCIAL).
     * CU06 — procesarContenido() público sin requerir login.
     */
    Route::post('verificacion', [VerificacionController::class, 'verificarContenido'])
        ->name('verificacion.procesar');

    /**
     * POST /api/v1/verificacion/stream
     * TSK-6.7: Canal Reactivo SSE (Híbrido A + B) para progreso en vivo y streaming de veredicto.
     */
    Route::match(['GET', 'POST'], 'verificacion/stream', [VerificacionController::class, 'verificarContenidoStream'])
        ->name('verificacion.stream');

    /**
     * GET /api/v1/verificacion/{idContenido}
     * Obtener resultado de un proceso de verificación previo.
     * CU04/CU06 — obtenerContenido()
     */
    Route::get('verificacion/{idContenido}', [VerificacionController::class, 'obtenerContenido'])
        ->whereUuid('idContenido')
        ->name('verificacion.obtener');

    /**
     * GET /api/v1/fuentes
     * Listar fuentes del grafo de credibilidad (acceso público).
     * CU07 — obtenerFuentes()
     */
    Route::get('fuentes', [VerificacionController::class, 'listarFuentes'])
        ->name('fuentes.listar');

    // ──────────────────────────────────────────────────────────────────────────
    // Rutas protegidas por autenticación Sanctum (opcional para usuarios registrados)
    // ──────────────────────────────────────────────────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // ── Auth ──────────────────────────────────────────────────────────────

        /**
         * POST /api/v1/auth/logout
         * Cerrar sesión y revocar token.
         */
        Route::post('auth/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');

        /**
         * GET /api/v1/auth/perfil
         * Obtener perfil del usuario autenticado.
         */
        Route::get('auth/perfil', [AuthController::class, 'perfil'])
            ->name('auth.perfil');

        /**
         * GET /api/v1/auth/datos-personales
         * Ley 1581 de 2012 — Descarga de datos personales (Hábeas Data).
         */
        Route::get('auth/datos-personales', [AuthController::class, 'datosPersonales'])
            ->name('auth.datos-personales');

        /**
         * DELETE /api/v1/auth/cuenta
         * Ley 1581 de 2012 — Derecho al olvido y supresión de cuenta.
         */
        Route::delete('auth/cuenta', [AuthController::class, 'eliminarCuenta'])
            ->name('auth.cuenta.eliminar');

        // ── Herramientas Avanzadas de Verificación ───────────────────────────

        /**
         * GET /api/v1/verificacion/archive/consultar
         * Consultar capturas históricas en Archive.org / Wayback Machine (TSK-3.2 & REQ-10).
         */
        Route::get('verificacion/archive/consultar', [VerificacionController::class, 'consultarArchive'])
            ->name('verificacion.archive.consultar');

        /**
         * POST /api/v1/verificacion/archive/descontextualizacion
         * Detectar descontextualización histórica de una URL (TSK-3.2 & REQ-10).
         */
        Route::post('verificacion/archive/descontextualizacion', [VerificacionController::class, 'detectarDescontextualizacion'])
            ->name('verificacion.archive.descontextualizacion');

        /**
         * POST /api/v1/verificacion/interlingue/detectar
         * Detección de idioma y mezcla bilingüe ES/EN (TSK-4.4 & REQ-19).
         */
        Route::post('verificacion/interlingue/detectar', [VerificacionController::class, 'detectarIdioma'])
            ->name('verificacion.interlingue.detectar');

        /**
         * POST /api/v1/verificacion/interlingue/traducir
         * Traducción bidireccional ES/EN preservando entidades y tecnicismos (TSK-4.4 & REQ-19).
         */
        Route::post('verificacion/interlingue/traducir', [VerificacionController::class, 'traducir'])
            ->name('verificacion.interlingue.traducir');

        /**
         * POST /api/v1/verificacion/interlingue/distorsion
         * Análisis de distorsión semántica, sensacionalismo y tergiversación (TSK-4.4 & REQ-19).
         */
        Route::post('verificacion/interlingue/distorsion', [VerificacionController::class, 'analizarDistorsionTraduccion'])
            ->name('verificacion.interlingue.distorsion');

        // ── CU03: Guardar Contenidos ─────────────────────────────────────────

        /**
         * POST /api/v1/historial/{idContenido}
         * Guardar contenido verificado en el historial personal.
         * CU03 — coordinarGuardado()
         */
        Route::post('historial/{idContenido}', [VerificacionController::class, 'guardarContenido'])
            ->whereUuid('idContenido')
            ->name('historial.guardar');

        // ── CU04: Historial de Contenidos ────────────────────────────────────

        /**
         * GET /api/v1/historial
         * Listar historial cronológico del usuario autenticado.
         * CU04 — consultarHistorialUsuario()
         */
        Route::get('historial', [VerificacionController::class, 'historialUsuario'])
            ->name('historial.listar');

        // ── CU01: Notas Complementarias ──────────────────────────────────────

        /**
         * POST /api/v1/contenidos/{idContenido}/notas
         * Agregar una nota comunitaria a un contenido verificado.
         * CU01 — procesarNota() / analizarNota()
         */
        Route::post('contenidos/{idContenido}/notas', [VerificacionController::class, 'agregarNota'])
            ->whereUuid('idContenido')
            ->name('notas.agregar');

        /**
         * GET /api/v1/contenidos/{idContenido}/notas
         * Listar notas complementarias de un contenido.
         */
        Route::get('contenidos/{idContenido}/notas', [VerificacionController::class, 'listarNotas'])
            ->whereUuid('idContenido')
            ->name('notas.listar');

        // ── CU02: Calificar Notas ────────────────────────────────────────────

        /**
         * PATCH /api/v1/notas/{idNota}/calificar
         * Votar la utilidad de una nota comunitaria (0.0 a 10.0).
         * CU02 — procesarCalificacion() / validarCalificacion()
         */
        Route::patch('notas/{idNota}/calificar', [VerificacionController::class, 'calificarNota'])
            ->whereUuid('idNota')
            ->name('notas.calificar');

        // ── CU07: Fuentes del Grafo de Credibilidad (Acciones de Usuario/Admin) ───

        /**
         * PATCH /api/v1/fuentes/{idFuente}/valorar
         * Votar confiabilidad de una fuente (usuario adulto).
         * CU07 — procesarValoracion()
         */
        Route::patch('fuentes/{idFuente}/valorar', [VerificacionController::class, 'valorarFuente'])
            ->whereUuid('idFuente')
            ->name('fuentes.valorar');

        /**
         * PATCH /api/v1/fuentes/{idFuente}/clasificar
         * Reclasificar una fuente (solo administrador).
         * CU07 FA1 — actualizarClasificacion()
         */
        Route::patch('fuentes/{idFuente}/clasificar', [VerificacionController::class, 'reclasificarFuente'])
            ->whereUuid('idFuente')
            ->name('fuentes.clasificar');

        /**
         * POST /api/v1/fuentes
         * Registrar nueva fuente en el grafo (solo administrador).
         */
        Route::post('fuentes', [VerificacionController::class, 'crearFuente'])
            ->name('fuentes.crear');

        /**
         * POST /api/v1/veredictos/{idVeredicto}/intervenir
         * Intervención humana / revisión editorial sobre veredicto (TSK-3.7 & REQ-13).
         * Roles permitidos: admin, editor.
         */
        Route::post('veredictos/{idVeredicto}/intervenir', [VerificacionController::class, 'intervenirVeredicto'])
            ->middleware('rol:admin,editor')
            ->whereUuid('idVeredicto')
            ->name('veredictos.intervenir');

        // ── Auditoría, Circuit Breaker y Telemetría (solo admin) ────────────

        /**
         * GET /api/v1/admin/circuit-breaker
         * Consultar métricas y estado del disyuntor de costes LLM (TSK-4.3).
         */
        Route::get('admin/circuit-breaker', [VerificacionController::class, 'metricasCircuitBreaker'])
            ->middleware('rol:admin')
            ->name('admin.circuit-breaker');

        /**
         * POST /api/v1/admin/circuit-breaker/reset
         * Restablecer manualmente el disyuntor de costes LLM (TSK-4.3).
         */
        Route::post('admin/circuit-breaker/reset', [VerificacionController::class, 'reiniciarCircuitBreaker'])
            ->middleware('rol:admin')
            ->name('admin.circuit-breaker.reset');

        /**
         * GET /api/v1/admin/logs-api
         * Consultar logs de auditoría de APIs externas (admin).
         * Query params: ?servicio=OpenAI&codigo_respuesta=500
         */
        Route::get('admin/logs-api', [VerificacionController::class, 'logsApi'])
            ->middleware('rol:admin')
            ->name('admin.logs-api');

        // ── TSK-4.6 & REQ-25: Auditoría para Reguladores y Medios ────────────
        Route::prefix('auditoria')
            ->middleware('rol:admin,editor')
            ->group(function () {
                /**
                 * GET /api/v1/auditoria/veredictos/{idContenido}
                 * Exportar expediente de auditoría forense individual (?formato=json|json-ld|csv).
                 */
                Route::get('veredictos/{idContenido}', [\App\Http\Controllers\AuditoriaController::class, 'exportarExpediente'])
                    ->whereUuid('idContenido')
                    ->name('auditoria.veredictos.exportar');

                /**
                 * GET /api/v1/auditoria/expedientes
                 * Exportar lote masivo de auditoría para reguladores (?formato=json|csv&desde=...&hasta=...).
                 */
                Route::get('expedientes', [\App\Http\Controllers\AuditoriaController::class, 'exportarLote'])
                    ->name('auditoria.expedientes.lote');

                /**
                 * GET /api/v1/auditoria/claim-review/{idContenido}
                 * Schema.org ClaimReview JSON-LD para Google Fact Check Tools / IFCN.
                 */
                Route::get('claim-review/{idContenido}', [\App\Http\Controllers\AuditoriaController::class, 'claimReview'])
                    ->whereUuid('idContenido')
                    ->name('auditoria.claimreview.obtener');
            });

    }); // end middleware auth:sanctum

    // =========================================================================
    // API PÚBLICA V1 PARA REDACCIONES SOCIAS (TSK-4.2 & REQ-16 / TSK-4.6 & REQ-25)
    // =========================================================================
    Route::prefix('public')
        ->middleware(['api.key', 'throttle:partners'])
        ->group(function () {
            /**
             * POST /api/v1/public/verificar
             * Verificar contenido para redacciones socias mediante API Key (REQ-16).
             */
            Route::post('verificar', [\App\Http\Controllers\PublicPartnerApiController::class, 'verificar'])
                ->name('public.verificar');

            /**
             * GET /api/v1/public/veredictos/{idContenido}
             * Consultar veredicto y contraste de un contenido verificado.
             */
            Route::get('veredictos/{idContenido}', [\App\Http\Controllers\PublicPartnerApiController::class, 'consultarVeredicto'])
                ->whereUuid('idContenido')
                ->name('public.veredictos.obtener');

            /**
             * GET /api/v1/public/fuentes
             * Consultar el Grafo de Credibilidad de 80 fuentes de DAGAZ.
             */
            Route::get('fuentes', [\App\Http\Controllers\PublicPartnerApiController::class, 'listarFuentes'])
                ->name('public.fuentes.listar');

            /**
             * GET /api/v1/public/health
             * Estado operativo del servicio de redacciones aliadas.
             */
            Route::get('health', [\App\Http\Controllers\PublicPartnerApiController::class, 'health'])
                ->name('public.health');

            /**
             * GET /api/v1/public/auditoria/{idContenido}
             * Exportar expediente de auditoría forense para redacciones socias con API Key (TSK-4.6 & REQ-25).
             */
            Route::get('auditoria/{idContenido}', [\App\Http\Controllers\AuditoriaController::class, 'exportarExpediente'])
                ->whereUuid('idContenido')
                ->name('public.auditoria.exportar');

            /**
             * GET /api/v1/public/claim-review/{idContenido}
             * ClaimReview JSON-LD para redacciones socias con API Key (TSK-4.6 & REQ-25).
             */
            Route::get('claim-review/{idContenido}', [\App\Http\Controllers\AuditoriaController::class, 'claimReview'])
                ->whereUuid('idContenido')
                ->name('public.claimreview.obtener');
        });

}); // end prefix v1

// ============================================================================
// Health Check — Endpoint de verificación de estado del sistema
// ============================================================================
Route::get('/health', function () {
    $dbConectada = false;
    try {
        DB::connection()->getPdo();
        $dbConectada = true;
    } catch (\Throwable) {
        $dbConectada = false;
    }

    return response()->json([
        'estado'     => $dbConectada ? 'operativo' : 'degradado',
        'sistema'    => 'DAGAZ',
        'version'    => (string) config('dagaz.version', '0.0.24'),
        'base_datos' => $dbConectada ? 'conectada' : 'desconectada',
        'entorno'    => app()->environment(),
        'timestamp'  => now()->toIso8601String(),
    ], $dbConectada ? 200 : 503);
})->name('health');

// ============================================================================
// Documentación OpenAPI 3.1 — Especificación del contrato REST de DAGAZ
// ============================================================================
Route::get('/docs', function (Request $request) {
    $path = storage_path('api-docs/openapi.yaml');
    if (! file_exists($path)) {
        return response()->json(['mensaje' => 'Especificación OpenAPI no encontrada.'], 404);
    }

    $spec = (string) file_get_contents($path);
    $serverUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/api/v1';

    if ($serverUrl !== 'http://localhost:8000/api/v1') {
        $descripcion = app()->isProduction()
            ? 'Servidor Activo (Producción)'
            : 'Servidor Activo';

        $spec = preg_replace(
            '/^servers:\R\s*-\s*url:\s*http:\/\/localhost:8000\/api\/v1\R\s*description:\s*Servidor de Desarrollo Local/m',
            "servers:\n  - url: {$serverUrl}\n    description: {$descripcion}\n  - url: http://localhost:8000/api/v1\n    description: Servidor de Desarrollo Local",
            $spec,
            1
        ) ?? $spec;
    }

    return response($spec, 200, [
        'Content-Type' => 'text/yaml; charset=UTF-8',
    ]);
})->name('api.docs');
