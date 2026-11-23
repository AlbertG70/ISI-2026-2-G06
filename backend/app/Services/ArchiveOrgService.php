<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ConectorArchiveInterface;
use App\Models\LogComunicadorApi;
use Carbon\Carbon;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Class ArchiveOrgService
 *
 * Implementación del conector con la API pública de Archive.org / Wayback Machine.
 * Requisito: TSK-3.2 & REQ-10.
 *
 * Provee herramientas para:
 * 1. Consultar snapshots recientes y disponibilidad.
 * 2. Recuperar el índice cronológico de capturas (CDX API).
 * 3. Detectar descontextualización histórica (reciclaje de noticias o enlaces antiguos).
 * 4. Auditar todas las consultas en `logs_comunicador_api`.
 *
 * @package App\Services
 */
class ArchiveOrgService implements ConectorArchiveInterface
{
    /**
     * URL base de la API de disponibilidad de Wayback Machine.
     */
    private string $baseUrl;

    /**
     * URL del servidor CDX de Archive.org para búsquedas cronológicas.
     */
    private string $cdxUrl;

    /**
     * Timeout en segundos para peticiones HTTP a Archive.org.
     */
    private int $timeout;

    /**
     * Días mínimos de antigüedad entre la primera captura y la fecha de verificación
     * para disparar una alerta de descontextualización temporal.
     */
    private int $umbralDias;

    public function __construct()
    {
        $this->baseUrl    = (string) config('dagaz.archive_org.base_url', 'https://archive.org/wayback/available');
        $this->cdxUrl     = (string) config('dagaz.archive_org.cdx_url', 'https://web.archive.org/cdx/search/cdx');
        $this->timeout    = (int) config('dagaz.archive_org.timeout', 15);
        $this->umbralDias = (int) config('dagaz.archive_org.umbral_descontextualizacion_dias', 365);
    }

    /**
     * {@inheritdoc}
     */
    public function consultarDisponibilidad(string $url, ?string $idProceso = null): array
    {
        $codigoHttp = 0;
        $detalleError = null;

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'DAGAZ-Verification-Platform/1.0 (+https://dagaz.com)',
                    'Accept'     => 'application/json',
                ])
                ->get($this->baseUrl, [
                    'url' => $url,
                ]);

            $codigoHttp = $response->status();
            $datos = $response->json();

            $snapshot = $datos['archived_snapshots']['closest'] ?? null;
            $disponible = !empty($snapshot) && ($snapshot['available'] ?? false) === true;

            $this->registrarLog(
                idProceso: $idProceso,
                tipoOperacion: 'historico_wayback',
                codigoRespuesta: $codigoHttp,
                detalleError: null
            );

            return [
                'disponible'  => $disponible,
                'url_captura' => $snapshot['url'] ?? null,
                'timestamp'   => $snapshot['timestamp'] ?? null,
                'codigo_http' => $codigoHttp,
                'error'       => null,
            ];

        } catch (RequestException $e) {
            $codigoHttp = $e->response?->status() ?? 502;
            $detalleError = $e->getMessage();
            Log::warning('[ArchiveOrgService::consultarDisponibilidad] Fallo HTTP en Wayback Machine', [
                'url'    => $url,
                'status' => $codigoHttp,
                'error'  => $detalleError,
            ]);
        } catch (\Throwable $e) {
            $codigoHttp = 500;
            $detalleError = $e->getMessage();
            Log::error('[ArchiveOrgService::consultarDisponibilidad] Error inesperado', [
                'url'   => $url,
                'error' => $detalleError,
            ]);
        }

        $this->registrarLog(
            idProceso: $idProceso,
            tipoOperacion: 'historico_wayback',
            codigoRespuesta: $codigoHttp,
            detalleError: $detalleError
        );

        return [
            'disponible'  => false,
            'url_captura' => null,
            'timestamp'   => null,
            'codigo_http' => $codigoHttp,
            'error'       => $detalleError,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function obtenerCapturasHistoricas(string $url, int $limite = 10, ?string $idProceso = null): array
    {
        $codigoHttp = 0;
        $detalleError = null;

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'DAGAZ-Verification-Platform/1.0 (+https://dagaz.com)',
                    'Accept'     => 'application/json',
                ])
                ->get($this->cdxUrl, [
                    'url'       => $url,
                    'output'    => 'json',
                    'limit'     => $limite,
                    'fl'        => 'timestamp,original,mimetype,statuscode,digest',
                    'filter'    => 'statuscode:200',
                ]);

            $codigoHttp = $response->status();
            $datos = $response->json();

            $capturas = [];

            if (is_array($datos) && count($datos) > 1) {
                // La fila 0 contiene los encabezados: ["timestamp","original","mimetype","statuscode","digest"]
                $filas = array_slice($datos, 1);

                foreach ($filas as $fila) {
                    if (is_array($fila) && count($fila) >= 4) {
                        $rawTs = (string) $fila[0];
                        $original = (string) $fila[1];
                        $mimetype = (string) $fila[2];
                        $statusCode = (string) $fila[3];

                        $fechaFormateada = $this->parsearTimestampWayback($rawTs);

                        $capturas[] = [
                            'timestamp'    => $rawTs,
                            'fecha_hora'   => $fechaFormateada,
                            'original'     => $original,
                            'mimetype'     => $mimetype,
                            'statuscode'   => $statusCode,
                            'url_wayback'  => "https://web.archive.org/web/{$rawTs}/{$original}",
                        ];
                    }
                }
            }

            $this->registrarLog(
                idProceso: $idProceso,
                tipoOperacion: 'busqueda_fuentes',
                codigoRespuesta: $codigoHttp,
                detalleError: null
            );

            return $capturas;

        } catch (\Throwable $e) {
            $codigoHttp = 500;
            $detalleError = $e->getMessage();

            $this->registrarLog(
                idProceso: $idProceso,
                tipoOperacion: 'busqueda_fuentes',
                codigoRespuesta: $codigoHttp,
                detalleError: $detalleError
            );

            Log::warning('[ArchiveOrgService::obtenerCapturasHistoricas] Fallo al consultar CDX API', [
                'url'   => $url,
                'error' => $detalleError,
            ]);

            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function detectarDescontextualizacion(string $url, ?string $fechaReclamada = null, ?string $idProceso = null): array
    {
        // 1. Obtener primera y última captura disponible
        $capturas = $this->obtenerCapturasHistoricas($url, 50, $idProceso);

        // Si CDX falló o no tiene capturas, intentar fallback con API de disponibilidad
        if (empty($capturas)) {
            $disp = $this->consultarDisponibilidad($url, $idProceso);
            if ($disp['disponible'] && !empty($disp['timestamp'])) {
                $capturas[] = [
                    'timestamp'   => $disp['timestamp'],
                    'fecha_hora'  => $this->parsearTimestampWayback($disp['timestamp']),
                    'original'    => $url,
                    'mimetype'    => 'text/html',
                    'statuscode'  => '200',
                    'url_wayback' => $disp['url_captura'] ?? "https://web.archive.org/web/{$disp['timestamp']}/{$url}",
                ];
            }
        }

        if (empty($capturas)) {
            return [
                'descontextualizado'     => false,
                'antiguedad_dias'        => 0,
                'primera_captura'        => null,
                'ultima_captura'         => null,
                'total_capturas'         => 0,
                'nivel_riesgo'           => 'BAJO',
                'diagnostico'            => 'No se encontraron capturas previas en Archive.org para este enlace o el dominio no ha sido indexado.',
                'evidencia_wayback_url'  => null,
            ];
        }

        // Ordenar cronológicamente por timestamp ascendente
        usort($capturas, fn ($a, $b) => strcmp($a['timestamp'], $b['timestamp']));

        $primera = $capturas[0];
        $ultima = end($capturas);

        $fechaPrimeraCaptura = $this->carbonDesdeTimestampWayback($primera['timestamp']);
        $fechaReferencia = $fechaReclamada ? Carbon::parse($fechaReclamada) : Carbon::now();

        $antiguedadDias = (int) abs($fechaReferencia->diffInDays($fechaPrimeraCaptura));
        $descontextualizado = $antiguedadDias >= $this->umbralDias;

        // Evaluar nivel de riesgo
        $nivelRiesgo = match (true) {
            $antiguedadDias >= 1095 => 'CRITICO', // >= 3 años
            $antiguedadDias >= 365  => 'ALTO',    // >= 1 año
            $antiguedadDias >= 90   => 'MEDIO',   // >= 3 meses
            default                 => 'BAJO',
        };

        // Construir diagnóstico explicativo
        if ($descontextualizado) {
            $anios = round($antiguedadDias / 365, 1);
            $diagnostico = sprintf(
                'Alerta de descontextualización histórica (REQ-10): Este contenido fue archivado en Wayback Machine por primera vez el %s (hace %d días / aprox. %.1f años). Compartirlo como novedad actual constituye una descontextualización temporal.',
                $primera['fecha_hora'],
                $antiguedadDias,
                $anios
            );
        } else {
            $diagnostico = sprintf(
                'Registro histórico congruente: La primera captura en Wayback Machine data del %s (%d días de antigüedad). No se detecta reciclaje anacrónico.',
                $primera['fecha_hora'],
                $antiguedadDias
            );
        }

        return [
            'descontextualizado'    => $descontextualizado,
            'antiguedad_dias'       => $antiguedadDias,
            'primera_captura'       => $primera['fecha_hora'],
            'ultima_captura'        => $ultima['fecha_hora'],
            'total_capturas'        => count($capturas),
            'nivel_riesgo'          => $nivelRiesgo,
            'diagnostico'           => $diagnostico,
            'evidencia_wayback_url' => $primera['url_wayback'],
        ];
    }

    /**
     * Convierte un timestamp compacto de Wayback Machine (YYYYMMDDhhmmss) a formato legible ISO / d/m/Y H:i:s.
     *
     * @param  string $rawTs Timestamp bruto de 14 dígitos (ej: 20180515120000).
     * @return string        Fecha formateada.
     */
    private function parsearTimestampWayback(string $rawTs): string
    {
        try {
            $carbon = $this->carbonDesdeTimestampWayback($rawTs);
            return $carbon->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $rawTs;
        }
    }

    /**
     * Genera una instancia Carbon a partir del formato numérico de Wayback Machine.
     *
     * @param  string $rawTs Formato YYYYMMDDhhmmss o YYYYMMDD.
     * @return \Carbon\Carbon
     */
    private function carbonDesdeTimestampWayback(string $rawTs): Carbon
    {
        $limpio = preg_replace('/\D/', '', $rawTs) ?? '';
        if (strlen($limpio) >= 14) {
            return Carbon::createFromFormat('YmdHis', substr($limpio, 0, 14)) ?: Carbon::now();
        }
        if (strlen($limpio) >= 8) {
            return Carbon::createFromFormat('Ymd', substr($limpio, 0, 8)) ?: Carbon::now();
        }
        return Carbon::now();
    }

    /**
     * Registra auditoría técnica en la tabla logs_comunicador_api.
     */
    private function registrarLog(
        ?string $idProceso,
        string $tipoOperacion,
        int $codigoRespuesta,
        ?string $detalleError
    ): void {
        try {
            LogComunicadorApi::create([
                'id_proceso'       => $idProceso,
                'servicio'         => 'Archive.org',
                'tipo_operacion'   => $tipoOperacion,
                'codigo_respuesta' => $codigoRespuesta,
                'detalle_error'    => $detalleError ? substr($detalleError, 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ArchiveOrgService] No se pudo guardar log en logs_comunicador_api: ' . $e->getMessage());
        }
    }
}
