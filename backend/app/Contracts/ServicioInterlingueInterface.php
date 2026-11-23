<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface ServicioInterlingueInterface
 *
 * Contrato para el servicio de recuperación y análisis interlingüe.
 * Requisito: TSK-4.4 & REQ-19 (Soporte de recuperación interlingüe: traducir y contrastar afirmaciones anglosajonas).
 *
 * @package App\Contracts
 */
interface ServicioInterlingueInterface
{
    /**
     * Detecta el idioma principal y calcula métricas de origen anglosajón del texto.
     *
     * @param  string $texto Texto a evaluar.
     * @return array{
     *     codigo: string,
     *     nombre: string,
     *     confianza: float,
     *     es_anglosajon: bool,
     *     proporcion_ingles: float,
     *     terminos_detectados: array<string>
     * }
     */
    public function detectarIdioma(string $texto): array;

    /**
     * Traduce un texto entre español e inglés preservando la terminología clave para fact-checking.
     *
     * @param  string $texto         Texto a traducir.
     * @param  string $idiomaDestino Código ISO del idioma destino ('es' o 'en').
     * @param  string $idiomaOrigen  Código ISO del idioma origen ('auto', 'es', 'en').
     * @return array{
     *     texto_traducido: string,
     *     idioma_origen: string,
     *     idioma_destino: string,
     *     metodo: string
     * }
     */
    public function traducir(string $texto, string $idiomaDestino = 'es', string $idiomaOrigen = 'auto'): array;

    /**
     * Detecta si una afirmación en español es una traducción distorsionada o sensacionalista
     * de una declaración o noticia original en inglés.
     *
     * @param  string $textoOriginalEn Declaración o titular original en inglés.
     * @param  string $textoTraducidoEs Versión traducida que circula en Colombia.
     * @return array{
     *     distorsionado: bool,
     *     score_fidelidad: float,
     *     tipo_distorsion: string,
     *     explicacion: string,
     *     discrepancias: array<string>
     * }
     */
    public function analizarDistorsionTraduccion(string $textoOriginalEn, string $textoTraducidoEs): array;

    /**
     * Genera un conjunto de consultas optimizadas en inglés a partir de una afirmación en español
     * para contrastar con agencias internacionales de Nivel 1 (Reuters, BBC, AP, WHO).
     *
     * @param  string $afirmacionEs Afirmación en español.
     * @return array{
     *     consulta_principal_en: string,
     *     consultas_alternativas: array<string>,
     *     palabras_clave_en: array<string>,
     *     fuentes_recomendadas: array<string>
     * }
     */
    public function generarConsultasInterlingues(string $afirmacionEs): array;
}
