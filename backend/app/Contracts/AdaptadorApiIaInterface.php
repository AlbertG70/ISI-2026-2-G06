<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface AdaptadorApiIaInterface
 *
 * Contrato (puerto) que deben cumplir todos los adaptadores de proveedores
 * de Inteligencia Artificial dentro del proyecto DAGAZ.
 *
 * Implementa el patrón Ports & Adapters (Hexagonal Architecture):
 * el núcleo del dominio sólo conoce esta interfaz; los drivers concretos
 * (OpenAI, Gemini, Ollama, etc.) son adaptadores intercambiables.
 *
 * @package App\Contracts
 * @version 1.0.0
 */
interface AdaptadorApiIaInterface
{
    /**
     * Analiza la naturaleza y tipo del contenido proporcionado.
     *
     * Clasifica el texto en categorías como: noticia, opinión, sátira,
     * publicidad, desinformación, contenido neutral, etc.
     *
     * @param  string $texto Texto a analizar.
     * @return string        Clasificación/naturaleza del contenido.
     */
    public function analizarNaturaleza(string $texto): string;

    /**
     * Detecta sesgo de lenguaje, sensacionalismo o amarillismo en el texto.
     *
     * Retorna un valor normalizado entre 0.0 (sin sesgo) y 1.0 (sesgo máximo).
     *
     * @param  string $texto Texto a evaluar.
     * @return float         Score de sesgo en el rango [0.0, 1.0].
     */
    public function detectarSesgoLenguaje(string $texto): float;

    /**
     * Verifica la coherencia semántica interna del texto.
     *
     * Evalúa si el contenido es consistente, si sus afirmaciones no se
     * contradicen entre sí y si el argumento es sólido lógicamente.
     *
     * @param  string $texto Texto a verificar.
     * @return array{score: float, justificacion: string, motivo_breve?: string|null}
     *              Arreglo con:
     *              - 'score'        (float)  : Valor de coherencia [0.0, 1.0].
     *              - 'justificacion' (string): Explicación razonada del score.
     */
    public function verificarCoherencia(string $texto): array;

    /**
     * Interpreta un prompt libre y retorna la respuesta generada por el LLM.
     *
     * Punto de acceso genérico para consultas que no encajan en los demás
     * métodos especializados.
     *
     * @param  string $consulta Prompt o consulta en lenguaje natural.
     * @return string           Respuesta generada por el modelo de lenguaje.
     */
    public function interpretarPrompt(string $consulta): string;

    /**
     * Descompone afirmaciones compuestas en unidades atómicas verificables.
     *
     * Divide un texto periodístico o declarativo en sus afirmaciones mínimas
     * independientes, facilitando la verificación individual de cada una.
     *
     * @param  string $texto Texto con afirmaciones compuestas.
     * @return array<int, string> Lista de afirmaciones atómicas extraídas.
     */
    public function descomponerAfirmaciones(string $texto): array;

    /**
     * Retorna el nombre identificador del proveedor de IA activo.
     *
     * Útil para logging, auditoría y presentación en UI.
     *
     * @return string Nombre del proveedor (p. ej. 'OpenAI', 'Gemini', 'Local (Ollama)').
     */
    public function getNombreProveedor(): string;
}
