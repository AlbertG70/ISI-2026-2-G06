<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contenido;
use App\Models\Fuente;
use App\Models\ProcesoVerificacion;
use App\Models\Veredicto;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * AuditoriaExportService — Servicio de Exportación de Auditoría Estructurada
 *
 * TSK-4.6 (Sprint 4 / REQ-25): Exportador de registros de auditoría y trazabilidad
 * para reguladores (MinTIC, SIC, UNESCO), observatorios y redacciones asociadas.
 *
 * Soporta:
 *  - Formato JSON forense integral con cadena de custodia e integridad SHA-256.
 *  - Formato JSON-LD con esquema ClaimReview de Schema.org / Google Fact Check Tools / IFCN.
 *  - Formato CSV tabular estandarizado (RFC 4180 con UTF-8 BOM para analistas y hojas de cálculo).
 *
 * Cumplimiento normativo:
 *  - Ley 1581 de 2012 de Hábeas Data (Colombia).
 *  - Código de Principios de la International Fact-Checking Network (IFCN).
 *  - Directrices de Transparencia Algorítmica y Explicabilidad de IA.
 *
 * @package App\Services
 */
class AuditoriaExportService
{
    /** Versión del esquema de auditoría forense de DAGAZ */
    public const VERSION_ESQUEMA = 'v1.0-auditable';

    /**
     * Genera un expediente de auditoría forense integral para un contenido verificado.
     *
     * @param  \App\Models\Contenido  $contenido
     * @return array<string, mixed>
     */
    public function generarExpedienteAuditoria(Contenido $contenido): array
    {
        $contenido->loadMissing([
            'veredicto.fuentes',
            'procesoActual.logsApi',
            'notas',
            'historial',
        ]);

        $veredicto = $contenido->veredicto;
        $proceso = $contenido->procesoActual;

        // Fuentes contrastadas con metadatos del Grafo
        $fuentesContraste = [];
        if ($veredicto && $veredicto->fuentes) {
            foreach ($veredicto->fuentes as $fuente) {
                /** @var Fuente $fuente */
                $fuentesContraste[] = [
                    'id_fuente'           => $fuente->id_fuente,
                    'nombre'              => $fuente->nombre,
                    'url_sitio'           => $fuente->url_sitio,
                    'tipo_fuente'         => $fuente->tipo_fuente,
                    'clasificacion'       => $fuente->getClasificacion(),
                    'nivel_grafo'         => $fuente->getNivel(),
                    'nivel_etiqueta'      => $fuente->getNivelEtiqueta(),
                    'indice_credibilidad' => (float) $fuente->indice_credibilidad,
                    'peso_en_veredicto'   => isset($fuente->pivot->peso_en_veredicto) ? (float) $fuente->pivot->peso_en_veredicto : null,
                    'cita_contraste'      => $fuente->pivot->cita_contraste ?? null,
                ];
            }
        }

        // Intervenciones editoriales humanas registradas en logs_comunicador_api
        $intervenciones = [];
        $trazabilidadLlamadas = [];
        if ($proceso && $proceso->logsApi) {
            foreach ($proceso->logsApi as $log) {
                $trazabilidadLlamadas[] = [
                    'id_log'           => $log->id_log,
                    'servicio'         => $log->servicio,
                    'tipo_operacion'   => $log->tipo_operacion,
                    'codigo_respuesta' => $log->codigo_respuesta,
                    'fecha_registro'   => $log->fecha_registro?->toIso8601String(),
                ];

                if ($log->tipo_operacion === 'intervencion_editorial') {
                    $detalle = null;
                    if ($log->detalle_error) {
                        $detalle = json_decode($log->detalle_error, true) ?: $log->detalle_error;
                    }
                    $intervenciones[] = [
                        'id_log'         => $log->id_log,
                        'fecha'          => $log->fecha_registro?->toIso8601String(),
                        'detalle'        => $detalle,
                    ];
                }
            }
        }

        // Notas comunitarias
        $notasData = [];
        $calificacionTotal = 0.0;
        foreach ($contenido->notas as $nota) {
            $calificacionTotal += (float) $nota->calificacion;
            $notasData[] = [
                'id_nota'        => $nota->id_nota,
                'calificacion'   => (float) $nota->calificacion,
                'contenido_nota' => $nota->contenido_nota,
                'fecha_creacion' => $nota->fecha_creacion?->toIso8601String(),
            ];
        }
        $promedioNotas = $contenido->notas->isNotEmpty()
            ? round($calificacionTotal / $contenido->notas->count(), 2)
            : 0.0;

        // Construir cuerpo auditable esencial para el cálculo del checksum
        $cuerpoAuditable = [
            'id_contenido'        => $contenido->id_contenido,
            'tipo_entrada'        => $contenido->tipo_entrada,
            'contenido_original'  => $contenido->contenido_original,
            'url_origen'          => $contenido->url_origen,
            'estado'              => $contenido->estado,
            'fecha_subida'        => $contenido->fecha_subida?->toIso8601String(),
            'id_proceso'          => $proceso?->id_proceso,
            'score_confiabilidad' => $proceso ? (float) $proceso->score_confiabilidad : null,
            'id_veredicto'        => $veredicto?->id_veredicto,
            'tipo_veredicto'      => $veredicto?->tipo_veredicto,
            'nivel_confiabilidad' => $veredicto ? (float) $veredicto->nivel_confiabilidad : null,
            'explicacion'         => $veredicto?->explicacion,
            'fuentes_hash'        => md5(json_encode($fuentesContraste)),
        ];

        $sha256 = $this->calcularChecksum($cuerpoAuditable);

        return [
            'metadatos_auditoria' => [
                'esquema'            => self::VERSION_ESQUEMA,
                'sistema'            => 'DAGAZ — Plataforma de Verificación de Información y Contraste de Fuentes',
                'version_sistema'    => '0.0.24',
                'marco_normativo'    => [
                    'Ley 1581 de 2012 (Hábeas Data y Protección de Datos Personales en Colombia)',
                    'Estándares de Fact-Checking IFCN / EFCSN (Transparencia de Fuentes y Metodología)',
                    'Circular Única de Transparencia de Algoritmos e IA (MinTIC / SIC Colombia)',
                ],
                'fecha_emision_reporte' => now()->toIso8601String(),
                'sha256_integridad'     => $sha256,
            ],
            'contenido' => [
                'id_contenido'       => $contenido->id_contenido,
                'tipo_entrada'       => $contenido->tipo_entrada,
                'contenido_original' => $contenido->contenido_original,
                'hash_contenido'     => hash('sha256', (string) $contenido->contenido_original),
                'url_origen'         => $contenido->url_origen,
                'estado'             => $contenido->estado,
                'fecha_subida'       => $contenido->fecha_subida?->toIso8601String(),
            ],
            'proceso_verificacion' => $proceso ? [
                'id_proceso'          => $proceso->id_proceso,
                'score_confiabilidad' => (float) $proceso->score_confiabilidad,
                'fecha_analisis'      => $proceso->fecha_analisis?->toIso8601String(),
                'analisis_lenguaje'   => $proceso->analisis_lenguaje,
                'contraste_temas'     => $proceso->contraste_temas,
                'total_llamadas_api'  => count($trazabilidadLlamadas),
            ] : null,
            'veredicto' => $veredicto ? [
                'id_veredicto'        => $veredicto->id_veredicto,
                'tipo_veredicto'      => $veredicto->tipo_veredicto,
                'nivel_confiabilidad' => (float) $veredicto->nivel_confiabilidad,
                'explicacion'         => $veredicto->explicacion,
                'fecha_emision'       => $veredicto->fecha_emision?->toIso8601String(),
                'tiene_intervencion_editorial' => count($intervenciones) > 0,
            ] : null,
            'fuentes_evidencia' => [
                'total_fuentes' => count($fuentesContraste),
                'detalle'       => $fuentesContraste,
            ],
            'intervenciones_editoriales' => $intervenciones,
            'notas_comunitarias' => [
                'total_notas'    => count($notasData),
                'promedio_votos' => $promedioNotas,
                'notas'          => $notasData,
            ],
            'trazabilidad_tecnica' => [
                'total_registros_log' => count($trazabilidadLlamadas),
                'logs'                => $trazabilidadLlamadas,
            ],
        ];
    }

    /**
     * Genera la especificación estructurada en formato JSON-LD ClaimReview (Schema.org).
     *
     * Compatible con Google Fact Check Tools Search API, Duke Reporters' Lab y la IFCN.
     *
     * @param  \App\Models\Contenido  $contenido
     * @return array<string, mixed>
     */
    public function generarClaimReview(Contenido $contenido): array
    {
        $contenido->loadMissing(['veredicto', 'procesoActual']);
        $veredicto = $contenido->veredicto;

        $tipoVeredicto = $veredicto?->tipo_veredicto ?? 'NO_VERIFICABLE';
        $ratingNumerico = $this->mapearRatingNumerico($tipoVeredicto);

        $afirmacionTexto = Str::limit($contenido->contenido_original ?? 'Contenido bajo verificación', 280);

        return [
            '@context'      => 'https://schema.org',
            '@type'         => 'ClaimReview',
            'datePublished' => $veredicto?->fecha_emision?->toIso8601String() ?? now()->toIso8601String(),
            'url'           => url("/api/v1/public/veredictos/{$contenido->id_contenido}"),
            'claimReviewed' => $afirmacionTexto,
            'author'        => [
                '@type' => 'Organization',
                'name'  => 'DAGAZ Verificación Colombia',
                'url'   => config('app.url', 'https://dagaz.com.co'),
            ],
            'reviewRating' => [
                '@type'             => 'Rating',
                'ratingValue'       => $ratingNumerico,
                'bestRating'        => 5,
                'worstRating'       => 1,
                'alternateName'     => $tipoVeredicto,
                'ratingExplanation' => $veredicto?->explicacion ?? 'Sin veredicto emitido aún.',
            ],
            'itemReviewed' => [
                '@type'         => 'Claim',
                'name'          => 'Afirmación evaluada en proceso DAGAZ',
                'appearance'    => [
                    '@type' => 'CreativeWork',
                    'url'   => $contenido->url_origen ?? url("/api/v1/verificacion/{$contenido->id_contenido}"),
                ],
                'datePublished' => $contenido->fecha_subida?->toIso8601String(),
                'author'        => [
                    '@type' => 'Person',
                    'name'  => 'Fuente no identificada / Redes Sociales',
                ],
            ],
        ];
    }

    /**
     * Genera un lote de expedientes estructurados con estadísticas agregadas.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Contenido>  $contenidos
     * @param  array<string, mixed>                                        $filtros
     * @return array<string, mixed>
     */
    public function generarLoteAuditoria(Collection $contenidos, array $filtros = []): array
    {
        $expedientes = [];
        $distribucionVeredictos = [
            'VERDADERO'      => 0,
            'FALSO'          => 0,
            'ENGAÑOSO'       => 0,
            'PARCIAL'        => 0,
            'NO_VERIFICABLE' => 0,
            'SIN_VEREDICTO'  => 0,
        ];

        foreach ($contenidos as $contenido) {
            $exp = $this->generarExpedienteAuditoria($contenido);
            $expedientes[] = $exp;

            $tipo = $exp['veredicto']['tipo_veredicto'] ?? 'SIN_VEREDICTO';
            if (isset($distribucionVeredictos[$tipo])) {
                $distribucionVeredictos[$tipo]++;
            } else {
                $distribucionVeredictos['SIN_VEREDICTO']++;
            }
        }

        $resumen = [
            'total_expedientes'      => count($expedientes),
            'distribucion_veredictos' => $distribucionVeredictos,
            'filtros_aplicados'      => $filtros,
            'fecha_generacion'       => now()->toIso8601String(),
        ];

        return [
            'resumen_auditoria' => $resumen,
            'sha256_lote'       => $this->calcularChecksum($resumen),
            'expedientes'       => $expedientes,
        ];
    }

    /**
     * Genera una cadena CSV (RFC 4180 con UTF-8 BOM) auditable a partir de una colección de contenidos.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Contenido>  $contenidos
     * @return string
     */
    public function generarCsvAuditable(Collection $contenidos): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('No fue posible abrir el flujo temporal para generar el archivo CSV.');
        }

        // BOM UTF-8 para garantizar apertura correcta en Excel sin distorsión de tildes ni caracteres especiales
        fputs($handle, "\xEF\xBB\xBF");

        // Encabezados tabulares auditables
        $headers = [
            'ID_CONTENIDO',
            'FECHA_SUBIDA',
            'TIPO_ENTRADA',
            'ESTADO',
            'AFIRMACION_ORIGINAL',
            'URL_ORIGEN',
            'ID_PROCESO',
            'SCORE_PROCESO',
            'ID_VEREDICTO',
            'TIPO_VEREDICTO',
            'NIVEL_CONFIABILIDAD',
            'EXPLICACION_VEREDICTO',
            'FECHA_EMISION',
            'TOTAL_FUENTES',
            'FUENTES_NOMBRES',
            'INTERVENCION_EDITORIAL',
            'TOTAL_NOTAS',
            'CHECKSUM_SHA256',
        ];
        fputcsv($handle, $headers, ',', '"', '\\');

        foreach ($contenidos as $contenido) {
            $exp = $this->generarExpedienteAuditoria($contenido);

            $fuentesNombres = collect($exp['fuentes_evidencia']['detalle'] ?? [])
                ->pluck('nombre')
                ->implode('; ');

            $fila = [
                $exp['contenido']['id_contenido'],
                $exp['contenido']['fecha_subida'] ?? '',
                $exp['contenido']['tipo_entrada'],
                $this->sanitizarTextoCsv($exp['contenido']['contenido_original'] ?? ''),
                $exp['contenido']['url_origen'] ?? '',
                $exp['contenido']['estado'],
                $exp['proceso_verificacion']['id_proceso'] ?? '',
                $exp['proceso_verificacion']['score_confiabilidad'] ?? '',
                $exp['veredicto']['id_veredicto'] ?? '',
                $exp['veredicto']['tipo_veredicto'] ?? '',
                $exp['veredicto']['nivel_confiabilidad'] ?? '',
                $this->sanitizarTextoCsv($exp['veredicto']['explicacion'] ?? ''),
                $exp['veredicto']['fecha_emision'] ?? '',
                $exp['fuentes_evidencia']['total_fuentes'] ?? 0,
                $fuentesNombres,
                !empty($exp['intervenciones_editoriales']) ? 'SI' : 'NO',
                $exp['notas_comunitarias']['total_notas'] ?? 0,
                $exp['metadatos_auditoria']['sha256_integridad'],
            ];

            fputcsv($handle, $fila, ',', '"', '\\');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        return $csvContent !== false ? $csvContent : '';
    }

    /**
     * Calcula el checksum SHA-256 canónico de un conjunto de datos para garantizar inmutabilidad.
     *
     * @param  array<string, mixed>  $datos
     * @return string
     */
    public function calcularChecksum(array $datos): string
    {
        ksort($datos);
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return hash('sha256', (string) $json);
    }

    /**
     * Mapea el tipo de veredicto textual a una escala numérica estándar (1 a 5).
     *
     * @param  string|null  $tipoVeredicto
     * @return int
     */
    public function mapearRatingNumerico(?string $tipoVeredicto): int
    {
        return match ($tipoVeredicto) {
            'VERDADERO'      => 5,
            'PARCIAL'        => 3,
            'ENGAÑOSO'       => 2,
            'FALSO'          => 1,
            default          => 0, // NO_VERIFICABLE o sin veredicto
        };
    }

    /**
     * Sanitiza el texto para evitar inyecciones de fórmulas en hojas de cálculo (CSV Injection).
     *
     * @param  string  $texto
     * @return string
     */
    private function sanitizarTextoCsv(string $texto): string
    {
        $limpio = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
        // Si comienza con =, +, -, @, prefijar con comilla simple
        if ($limpio !== '' && in_array($limpio[0], ['=', '+', '-', '@'], true)) {
            $limpio = "'" . $limpio;
        }
        return $limpio;
    }
}
