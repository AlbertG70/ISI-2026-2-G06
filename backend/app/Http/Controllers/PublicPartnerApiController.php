<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contenido;
use App\Models\Fuente;
use App\Services\PartnerApiService;
use App\Services\VerificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PublicPartnerApiController — API Pública v1 para Redacciones Socias
 *
 * TSK-4.2 (Sprint 4 / REQ-16): Endpoints REST M2M para integración de salas
 * de prensa de medios aliados y verificadores (El Espectador, ColombiaCheck, La Silla Vacía).
 * Autenticado mediante cabecera X-API-KEY.
 *
 * @package App\Http\Controllers
 */
class PublicPartnerApiController extends Controller
{
    public function __construct(
        private readonly VerificacionService $verificacionService,
        private readonly PartnerApiService $partnerService
    ) {}

    /**
     * Verificar un contenido editorial o viral para una redacción socia.
     *
     * POST /api/v1/public/verificar
     * Headers: X-API-KEY: dagaz_partner_...
     * Body: { "tipo_entrada": "TEXTO", "contenido_original": "..." }
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificar(Request $request): JsonResponse
    {
        $socio = $request->attributes->get('socio');

        $validated = $request->validate([
            'tipo_entrada'       => ['required', 'string', 'in:TEXTO,URL,IMAGEN_OCR,RED_SOCIAL,texto,url,imagen,audio,video,imagen_ocr,red_social'],
            'contenido_original' => ['required_without_all:url_origen,archivo', 'nullable', 'string', 'min:10', 'max:10000'],
            'url_origen'         => ['required_without_all:contenido_original,archivo', 'nullable', 'url', 'max:2048'],
            'archivo'            => ['sometimes', 'nullable', 'file', 'max:20480'], // 20MB máx
        ]);

        $usuarioSocio = $this->partnerService->obtenerUsuarioAsociado($socio);

        try {
            $resultado = $this->verificacionService->procesarContenido(
                tipoEntrada:       $validated['tipo_entrada'],
                contenidoOriginal: $validated['contenido_original'] ?? null,
                urlOrigen:         $validated['url_origen'] ?? null,
                archivo:           $request->file('archivo'),
                idUsuario:         $usuarioSocio->id_usuario,
            );

            $idProceso = $resultado['proceso']['id_proceso'] ?? null;

            $this->partnerService->registrarAuditoria(
                idSocio:         $socio['id_socio'],
                tipoOperacion:   'verificacion_socio',
                codigoRespuesta: 200,
                detalleError:    null,
                idProceso:       $idProceso
            );

            return response()->json([
                'version_api' => 'v1.0',
                'socio' => [
                    'id_socio' => $socio['id_socio'],
                    'nombre'   => $socio['nombre'],
                ],
                'solicitud' => [
                    'id_contenido' => $resultado['contenido']['id_contenido'] ?? null,
                    'id_proceso'   => $idProceso,
                    'tipo_entrada' => $resultado['contenido']['tipo_entrada'] ?? $validated['tipo_entrada'],
                    'estado'       => $resultado['contenido']['estado'] ?? 'VERIFICADO',
                    'timestamp'    => now()->toIso8601String(),
                ],
                'veredicto' => [
                    'tipo_veredicto'      => $resultado['veredicto']['tipo_veredicto'] ?? 'NO_VERIFICABLE',
                    'nivel_confiabilidad' => (float) ($resultado['veredicto']['nivel_confiabilidad'] ?? 0.0),
                    'explicacion'         => $resultado['veredicto']['explicacion'] ?? '',
                ],
                'fuentes_contraste' => $resultado['fuentes'] ?? [],
                'analisis_avanzado' => [
                    'interlingue'             => $resultado['interlingue'] ?? null,
                    'archive_org'             => $resultado['archive_org'] ?? null,
                    'multi_modelo_evaluacion' => $resultado['multi_modelo_evaluacion'] ?? null,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error('[PublicPartnerApi] Error durante la verificación de redacción socia: ' . $e->getMessage(), [
                'socio' => $socio['id_socio'] ?? 'desconocido',
                'trace' => $e->getTraceAsString(),
            ]);

            $this->partnerService->registrarAuditoria(
                idSocio:         $socio['id_socio'] ?? 'desconocido',
                tipoOperacion:   'verificacion_socio_error',
                codigoRespuesta: 500,
                detalleError:    $e->getMessage()
            );

            return response()->json([
                'error'   => 'server_error',
                'mensaje' => 'Error al procesar la verificación editorial: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Consultar el veredicto y contraste de un contenido verificado previamente.
     *
     * GET /api/v1/public/veredictos/{idContenido}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idContenido
     * @return \Illuminate\Http\JsonResponse
     */
    public function consultarVeredicto(Request $request, string $idContenido): JsonResponse
    {
        $socio = $request->attributes->get('socio');

        $contenido = Contenido::with(['veredicto.fuentes', 'procesoActual', 'notas'])->find($idContenido);

        if (! $contenido) {
            return response()->json([
                'error'   => 'not_found',
                'mensaje' => 'Contenido no encontrado en el sistema DAGAZ.',
            ], 404);
        }

        $veredicto = $contenido->veredicto;
        $proceso = $contenido->procesoActual;

        $this->partnerService->registrarAuditoria(
            idSocio:         $socio['id_socio'],
            tipoOperacion:   'consulta_veredicto',
            codigoRespuesta: 200,
            detalleError:    null,
            idProceso:       $proceso?->id_proceso
        );

        return response()->json([
            'version_api' => 'v1.0',
            'socio' => [
                'id_socio' => $socio['id_socio'],
                'nombre'   => $socio['nombre'],
            ],
            'contenido' => [
                'id_contenido'       => $contenido->id_contenido,
                'tipo_entrada'       => $contenido->tipo_entrada,
                'contenido_original' => $contenido->contenido_original,
                'url_origen'         => $contenido->url_origen,
                'estado'             => $contenido->estado,
                'fecha_subida'       => $contenido->fecha_subida?->toIso8601String(),
            ],
            'veredicto' => $veredicto ? [
                'id_veredicto'        => $veredicto->id_veredicto,
                'tipo_veredicto'      => $veredicto->tipo_veredicto,
                'nivel_confiabilidad' => (float) $veredicto->nivel_confiabilidad,
                'explicacion'         => $veredicto->explicacion,
                'fuentes'             => $veredicto->fuentes->map(fn ($f) => [
                    'id_fuente'           => $f->id_fuente,
                    'nombre'              => $f->nombre,
                    'tipo_fuente'         => $f->tipo_fuente,
                    'nivel'               => $f->getNivel(),
                    'nivel_etiqueta'      => $f->getNivelEtiqueta(),
                    'indice_credibilidad' => (float) $f->indice_credibilidad,
                    'cita_contraste'      => $f->pivot->cita_contraste ?? null,
                ]),
            ] : null,
        ]);
    }

    /**
     * Consultar el Grafo de Credibilidad de DAGAZ para redacciones socias.
     *
     * GET /api/v1/public/fuentes
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listarFuentes(Request $request): JsonResponse
    {
        $socio = $request->attributes->get('socio');

        $query = Fuente::orderByDesc('indice_credibilidad')->orderBy('tipo_fuente');

        if ($request->has('tipo_fuente')) {
            $query->where('tipo_fuente', $request->string('tipo_fuente'));
        }

        if ($request->filled('buscar')) {
            $operadorLike = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
            $query->where('nombre', $operadorLike, '%' . $request->string('buscar') . '%');
        }

        $coleccion = $query->get();

        if ($request->has('nivel')) {
            $nivelFiltro = (int) $request->input('nivel');
            $coleccion = $coleccion->filter(fn ($f) => $f->getNivel() === $nivelFiltro)->values();
        }

        $fuentes = $coleccion->map(fn ($f) => [
            'id_fuente'           => $f->id_fuente,
            'nombre'              => $f->nombre,
            'url_sitio'           => $f->url_sitio,
            'tipo_fuente'         => $f->tipo_fuente,
            'clasificacion'       => $f->getClasificacion(),
            'nivel'               => $f->getNivel(),
            'nivel_etiqueta'      => $f->getNivelEtiqueta(),
            'indice_credibilidad' => (float) $f->indice_credibilidad,
        ]);

        return response()->json([
            'version_api' => 'v1.0',
            'socio' => [
                'id_socio' => $socio['id_socio'],
                'nombre'   => $socio['nombre'],
            ],
            'total'   => $fuentes->count(),
            'fuentes' => $fuentes,
        ]);
    }

    /**
     * Endpoint de disponibilidad y diagnóstico del servicio para partners.
     *
     * GET /api/v1/public/health
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function health(Request $request): JsonResponse
    {
        $socio = $request->attributes->get('socio');

        return response()->json([
            'status'         => 'operational',
            'servicio'       => 'DAGAZ Public Partner API v1',
            'socio_activo'   => $socio['nombre'] ?? 'Invitado',
            'timestamp'      => now()->toIso8601String(),
            'version'        => '1.0.0',
            'rate_limit'     => $socio['rate_limit_minute'] ?? 60,
            'sla'            => '99.9%',
        ]);
    }
}
