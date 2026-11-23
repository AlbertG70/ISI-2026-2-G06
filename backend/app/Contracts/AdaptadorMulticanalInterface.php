<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface AdaptadorMulticanalInterface
 *
 * Contrato (puerto) que define la capacidad de ingestión de contenido
 * desde múltiples canales y formatos dentro del proyecto DAGAZ.
 *
 * Los implementadores deben ser capaces de normalizar entradas heterogéneas
 * (texto plano, URL, imagen, audio, video) a una estructura común que el
 * dominio pueda procesar.
 *
 * @package App\Contracts
 * @version 1.0.0
 */
interface AdaptadorMulticanalInterface
{
    /**
     * Procesa una entrada según su tipo y retorna el contenido normalizado.
     *
     * Actúa como punto de entrada unificado; delega a los métodos
     * especializados según el valor de $tipo.
     *
     * @param  string $tipo  Tipo de entrada: 'texto', 'url', 'imagen', 'audio', 'video'.
     * @param  mixed  $datos Datos de la entrada (ruta de archivo, URL, texto plano, etc.).
     * @return array{
     *     tipo: string,
     *     texto_extraido: string,
     *     metadatos: array<string, mixed>,
     *     error: string|null
     * } Contenido normalizado con texto extraído y metadatos asociados.
     */
    public function procesarEntrada(string $tipo, mixed $datos): array;

    /**
     * Extrae texto de una imagen usando Reconocimiento Óptico de Caracteres (OCR).
     *
     * Delega al microservicio Python de OCR configurado en la variable
     * de entorno `OCR_SERVICE_URL`.
     *
     * @param  string $rutaImagen Ruta absoluta o URL accesible de la imagen.
     * @return string             Texto extraído de la imagen, o cadena vacía si falla.
     */
    public function extraerTextoOCR(string $rutaImagen): string;

    /**
     * Transcribe un archivo de audio a texto (Speech-to-Text).
     *
     * Delega al microservicio Python STT configurado en la variable
     * de entorno `STT_SERVICE_URL`.
     *
     * @param  string $rutaAudio Ruta absoluta o URL accesible del archivo de audio.
     * @return string            Transcripción del audio, o cadena vacía si falla.
     */
    public function transcribirAudio(string $rutaAudio): string;

    /**
     * Extrae y estructura el contenido de una URL (red social o sitio web).
     *
     * Realiza una petición HTTP al recurso indicado y procesa el HTML
     * resultante para obtener el contenido textual relevante.
     *
     * @param  string $url URL del recurso a escuchar/monitorear.
     * @return array{
     *     url: string,
     *     titulo: string,
     *     texto: string,
     *     status_code: int,
     *     error: string|null
     * } Contenido estructurado del recurso.
     */
    public function escucharRedSocial(string $url): array;

    /**
     * Retorna la lista de canales de entrada habilitados en el sistema.
     *
     * @return array<int, string> Lista de tipos de canal soportados.
     */
    public function getCanalesHabilitados(): array;
}
