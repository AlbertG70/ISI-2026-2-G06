<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contenido;
use App\Models\Usuario;
use App\Services\AuditoriaExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AuditoriaController — Controlador de Auditoría y Trazabilidad Algorítmica
 *
 * TSK-4.6 (Sprint 4 / REQ-25): Endpoints para exportación de registros auditables
 * para reguladores (MinTIC, SIC, UNESCO), observatorios y redacciones socias.
 *
 * Formatos soportados:
 *  - JSON forense estructurado con integridad SHA-256 (`?formato=json`).
 *  - JSON-LD con esquema ClaimReview de Schema.org / Google Fact Check (`?formato=json-ld`).
 *  - CSV tabular descargable con UTF-8 BOM (`?formato=csv`).
 *
 * @package App\Http\Controllers
 */
class AuditoriaController extends Controller
{
    public function __construct(
        private readonly AuditoriaExportService $auditoriaService
    ) {}

    /**
     * Exportar expediente de auditoría forense individual para un contenido verificado.
     *
     * GET /api/v1/auditoria/veredictos/{idContenido}
     * GET /api/v1/public/auditoria/{idContenido}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idContenido
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportarExpediente(Request $request, string $idContenido): Response
    {
        $contenido = Contenido::with([
            'veredicto.fuentes',
            'procesoActual.logsApi',
            'notas',
            'historial',
        ])->find($idContenido);

        if (! $contenido) {
            return new JsonResponse([
                'error'   => 'not_found',
                'mensaje' => 'Contenido no encontrado en el sistema DAGAZ.',
            ], 404);
        }

        $formato = strtolower((string) $request->query('formato', 'json'));

        return match ($formato) {
            'json' => new JsonResponse(
                $this->auditoriaService->generarExpedienteAuditoria($contenido),
                200,
                ['Content-Type' => 'application/json; charset=UTF-8']
            ),
            'json-ld', 'claimreview', 'claim-review' => new JsonResponse(
                $this->auditoriaService->generarClaimReview($contenido),
                200,
                ['Content-Type' => 'application/ld+json; charset=UTF-8']
            ),
            'csv' => response(
                $this->auditoriaService->generarCsvAuditable(collect([$contenido])),
                200,
                [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"expediente-dagaz-{$idContenido}.csv\"",
                ]
            ),
            default => new JsonResponse([
                'error'            => 'formato_no_soportado',
                'mensaje'          => "El formato '{$formato}' no es soportado. Valores válidos: 'json', 'json-ld', 'csv'.",
                'formatos_validos' => ['json', 'json-ld', 'csv'],
            ], 422),
        };
    }

    /**
     * Exportar lote masivo de expedientes para reguladores y observatorios.
     *
     * GET /api/v1/auditoria/expedientes
     *
     * Parámetros de consulta:
     *  - desde: YYYY-MM-DD
     *  - hasta: YYYY-MM-DD
     *  - tipo_veredicto: VERDADERO|FALSO|ENGAÑOSO|PARCIAL|NO_VERIFICABLE
     *  - tipo_entrada: TEXTO|URL|IMAGEN_OCR|RED_SOCIAL
     *  - formato: json|csv (por defecto: json)
     *  - limite: int (1 a 500, por defecto: 100)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportarLote(Request $request): Response
    {
        $validated = $request->validate([
            'desde'          => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hasta'          => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'tipo_veredicto' => ['sometimes', 'nullable', 'string', 'in:VERDADERO,FALSO,ENGAÑOSO,PARCIAL,NO_VERIFICABLE'],
            'tipo_entrada'   => ['sometimes', 'nullable', 'string', 'in:TEXTO,URL,IMAGEN_OCR,RED_SOCIAL'],
            'formato'        => ['sometimes', 'string', 'in:json,csv'],
            'limite'         => ['sometimes', 'integer', 'min:1', 'max:500'],
        ]);

        $query = Contenido::query()
            ->with([
                'veredicto.fuentes',
                'procesoActual.logsApi',
                'notas',
                'historial',
            ]);

        if (! empty($validated['desde'])) {
            $query->where('fecha_subida', '>=', Carbon::parse($validated['desde'])->startOfDay());
        }

        if (! empty($validated['hasta'])) {
            $query->where('fecha_subida', '<=', Carbon::parse($validated['hasta'])->endOfDay());
        }

        if (! empty($validated['tipo_entrada'])) {
            $query->where('tipo_entrada', $validated['tipo_entrada']);
        }

        if (! empty($validated['tipo_veredicto'])) {
            $query->whereHas('veredicto', fn ($qv) => $qv->where('tipo_veredicto', $validated['tipo_veredicto']));
        }

        $limite = (int) ($validated['limite'] ?? 100);
        $contenidos = $query->orderByDesc('fecha_subida')->limit($limite)->get();

        $formato = strtolower((string) ($validated['formato'] ?? 'json'));

        if ($formato === 'csv') {
            $fechaArchivo = now()->format('Ymd_His');
            return response(
                $this->auditoriaService->generarCsvAuditable($contenidos),
                200,
                [
                    'Content-Type'        => 'text/csv; charset=UTF-8',
                    'Content-Disposition' => "attachment; filename=\"auditoria-dagaz-lote-{$fechaArchivo}.csv\"",
                ]
            );
        }

        return new JsonResponse(
            $this->auditoriaService->generarLoteAuditoria($contenidos, $validated),
            200,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    /**
     * Endpoint directo para obtener el Schema.org ClaimReview JSON-LD de un veredicto.
     *
     * GET /api/v1/auditoria/claim-review/{idContenido}
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $idContenido
     * @return \Illuminate\Http\JsonResponse
     */
    public function claimReview(Request $request, string $idContenido): JsonResponse
    {
        $contenido = Contenido::with(['veredicto', 'procesoActual'])->find($idContenido);

        if (! $contenido) {
            return new JsonResponse([
                'error'   => 'not_found',
                'mensaje' => 'Contenido no encontrado en el sistema DAGAZ.',
            ], 404);
        }

        return new JsonResponse(
            $this->auditoriaService->generarClaimReview($contenido),
            200,
            ['Content-Type' => 'application/ld+json; charset=UTF-8']
        );
    }
}
