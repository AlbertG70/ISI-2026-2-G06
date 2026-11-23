<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface ConectorArchiveInterface
 *
 * Contrato para el conector con Archive.org / Wayback Machine.
 * Requisito: TSK-3.2 & REQ-10 (Recuperación de versiones históricas para detectar descontextualización).
 *
 * Permite auditar si una noticia, imagen o enlace que circula en la actualidad
 * corresponde a hechos de años anteriores presentados maliciosamente como vigentes.
 *
 * @package App\Contracts
 */
interface ConectorArchiveInterface
{
    /**
     * Consulta la disponibilidad inmediata de una captura reciente de la URL en Wayback Machine.
     *
     * @param  string      $url        URL a consultar.
     * @param  string|null $idProceso  ID del proceso de verificación para telemetría en logs.
     * @return array{
     *     disponible: bool,
     *     url_captura: ?string,
     *     timestamp: ?string,
     *     codigo_http: int,
     *     error: ?string
     * }
     */
    public function consultarDisponibilidad(string $url, ?string $idProceso = null): array;

    /**
     * Recupera el histórico de snapshots de la URL utilizando la API CDX de Archive.org.
     *
     * @param  string      $url        URL de origen.
     * @param  int         $limite     Límite de registros a recuperar.
     * @param  string|null $idProceso  ID del proceso para trazabilidad.
     * @return array<int, array{
     *     timestamp: string,
     *     fecha_hora: string,
     *     original: string,
     *     mimetype: string,
     *     statuscode: string,
     *     url_wayback: string
     * }>
     */
    public function obtenerCapturasHistoricas(string $url, int $limite = 10, ?string $idProceso = null): array;

    /**
     * Analiza si existe discrepancia temporal entre la primera aparición archivada en la web
     * y la fecha actual o reclamada por el emisor (Descontextualización Temporal).
     *
     * @param  string      $url             URL a evaluar.
     * @param  string|null $fechaReclamada  Fecha en que supuestamente ocurrió el hecho (Y-m-d).
     * @param  string|null $idProceso       ID del proceso para trazabilidad.
     * @return array{
     *     descontextualizado: bool,
     *     antiguedad_dias: int,
     *     primera_captura: ?string,
     *     ultima_captura: ?string,
     *     total_capturas: int,
     *     nivel_riesgo: string,
     *     diagnostico: string,
     *     evidencia_wayback_url: ?string
     * }
     */
    public function detectarDescontextualizacion(string $url, ?string $fechaReclamada = null, ?string $idProceso = null): array;
}
