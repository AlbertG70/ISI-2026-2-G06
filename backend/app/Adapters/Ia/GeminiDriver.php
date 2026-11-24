<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Contracts\AdaptadorApiIaInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

/**
 * Class GeminiDriver
 *
 * Adaptador concreto para la API de Google Gemini (gemini-2.0-flash y variantes).
 *
 * Implementa el contrato AdaptadorApiIaInterface siguiendo el patrón
 * Strategy/Adapter: el núcleo del dominio DAGAZ es completamente ajeno
 * al proveedor subyacente.
 *
 * Diferencias principales con OpenAI:
 *  - El endpoint incluye el nombre del modelo y la API key como query param.
 *  - El payload usa la estructura `contents[].parts[].text` de Google.
 *  - La respuesta se extrae de `candidates[0].content.parts[0].text`.
 *
 * Configuración esperada:
 *  - $apiKey : Clave de API de Google AI Studio (env GEMINI_API_KEY).
 *  - $modelo : Nombre del modelo, por defecto 'gemini-2.0-flash'.
 *  - $maxTokens: Límite de tokens en la respuesta.
 *
 * @package App\Adapters\Ia
 * @version 1.0.0
 */
class GeminiDriver implements AdaptadorApiIaInterface
{
    /**
     * URL base del endpoint de Google Generative Language API.
     *
     * @var string
     */
    private string $endpointBase = 'https://generativelanguage.googleapis.com/v1beta/models';

    /**
     * Clave de autenticación de la API de Google.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Identificador del modelo de Gemini a utilizar.
     *
     * @var string
     */
    private string $modelo;

    /**
     * Número máximo de tokens permitidos en la respuesta del LLM.
     *
     * @var int
     */
    private int $maxTokens;

    /**
     * Modelos alternativos ante HTTP 404/429/503 del modelo principal.
     *
     * @var array<int, string>
     */
    private array $fallbackModelos;

    /**
     * Crea una nueva instancia del driver de Gemini.
     *
     * @param string             $apiKey          Clave de API de Google AI.
     * @param string             $modelo          Modelo a usar (por defecto 'gemini-3.6-flash').
     * @param int                $maxTokens       Límite de tokens en la respuesta (por defecto 2000).
     * @param array<int, string> $fallbackModelos Modelos alternativos de respaldo ante 404/429/503.
     */
    public function __construct(
        string $apiKey,
        string $modelo = 'gemini-3.6-flash',
        int $maxTokens = 2000,
        array $fallbackModelos = []
    ) {
        $this->apiKey          = trim($apiKey);
        $this->modelo          = trim($modelo) ?: 'gemini-3.6-flash';
        $this->maxTokens       = $maxTokens;
        $this->fallbackModelos = array_values(array_unique(array_filter(
            array_map(fn ($m) => trim((string) $m), $fallbackModelos),
            fn ($m) => $m !== '' && $m !== $this->modelo
        )));
    }

    /**
     * Construye el endpoint completo incluyendo el modelo y la acción.
     *
     * @return string URL completa del endpoint de generateContent.
     */
    private function buildEndpoint(?string $modelo = null): string
    {
        $modeloObjetivo = $modelo ?: $this->modelo;
        return "{$this->endpointBase}/{$modeloObjetivo}:generateContent?key={$this->apiKey}";
    }

    /**
     * Realiza una llamada HTTP a la API de Google Gemini.
     *
     * Construye el payload en el formato `contents` de Google Generative Language,
     * con instrucción de sistema separada y mensaje del usuario.
     *
     * @param  string $instruccionSistema Prompt de sistema con el rol y restricciones.
     * @param  string $mensajeUsuario     Contenido aportado por el usuario/texto a analizar.
     * @param  float  $temperatura        Creatividad del modelo [0.0, 1.0].
     * @return string                     Texto generado por el modelo.
     *
     * @throws \RuntimeException Si la API retorna un error o la respuesta es inesperada.
     */
    private function llamarApi(
        string $instruccionSistema,
        string $mensajeUsuario,
        float $temperatura = 0.2
    ): string {
        $payload = [
            'system_instruction' => [
                'parts' => [['text' => $instruccionSistema]],
            ],
            'contents'           => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $mensajeUsuario]],
                ],
            ],
            'generationConfig'   => [
                'temperature'     => $temperatura,
                'maxOutputTokens' => $this->maxTokens,
            ],
        ];

        $modelosIntentar = array_merge([$this->modelo], $this->fallbackModelos);
        $ultimoRequestException = null;

        foreach ($modelosIntentar as $idx => $modeloActual) {
            try {
                $http = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(60);

                if (app()->environment('local', 'testing') || DIRECTORY_SEPARATOR === '\\') {
                    $http = $http->withoutVerifying();
                }

                $response = $http->post($this->buildEndpoint($modeloActual), $payload);

                $response->throw();

                /** @var array $body */
                $body = $response->json();

                $parts = $body['candidates'][0]['content']['parts'] ?? [];
                $textos = [];
                foreach ($parts as $part) {
                    if (!empty($part['thought'])) {
                        continue;
                    }
                    if (isset($part['text']) && is_string($part['text'])) {
                        $textos[] = $part['text'];
                    }
                }

                $contenido = trim(implode("\n", $textos));
                if ($contenido === '' && isset($parts[0]['text']) && is_string($parts[0]['text'])) {
                    $contenido = trim($parts[0]['text']);
                }

                if (str_contains($contenido, '</think>')) {
                    $partes    = explode('</think>', $contenido);
                    $contenido = trim((string) end($partes));
                } elseif (str_starts_with($contenido, '<think>')) {
                    $contenido = trim((string) preg_replace('/^<think>.*?$/s', '', $contenido));
                }

                return $contenido;
            } catch (RequestException $e) {
                $ultimoRequestException = $e;
                $status = $e->response?->status();
                $quedanFallbacks = $idx < count($modelosIntentar) - 1;

                if ($quedanFallbacks && in_array($status, [404, 429, 503], true)) {
                    Log::warning('[GeminiDriver] Modelo principal indisponible, probando respaldo.', [
                        'modelo_fallido' => $modeloActual,
                        'status'         => $status,
                        'siguiente'      => $modelosIntentar[$idx + 1],
                    ]);
                    continue;
                }

                Log::error('[GeminiDriver] Error en la llamada a la API de Gemini.', [
                    'modelo'    => $modeloActual,
                    'mensaje'   => $e->getMessage(),
                    'status'    => $status,
                    'respuesta' => $e->response?->body(),
                ]);
                throw new \RuntimeException(
                    'Error al comunicarse con la API de Gemini: ' . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            } catch (\Throwable $e) {
                Log::error('[GeminiDriver] Error inesperado al llamar a Gemini.', [
                    'modelo'  => $modeloActual,
                    'mensaje' => $e->getMessage(),
                ]);
                throw new \RuntimeException(
                    'Error inesperado en GeminiDriver: ' . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        throw new \RuntimeException(
            'Error al comunicarse con la API de Gemini: ' . ($ultimoRequestException?->getMessage() ?? 'Sin modelos disponibles'),
            (int) ($ultimoRequestException?->getCode() ?? 0),
            $ultimoRequestException
        );
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $texto Texto a analizar.
     * @return string        Naturaleza/tipo del contenido detectado.
     */
    public function analizarNaturaleza(string $texto): string
    {
        $sistema = 'Eres un analizador experto de contenido mediático. Clasifica el texto recibido en UNA de las siguientes categorías: noticia, opinión, sátira, publicidad, desinformación, propaganda, contenido neutral, otro. Responde ÚNICAMENTE con el nombre de la categoría, en minúsculas y sin puntuación adicional.';

        try {
            return $this->llamarApi($sistema, "Clasifica el siguiente texto:\n\n{$texto}", 0.1);
        } catch (\RuntimeException $e) {
            Log::warning('[GeminiDriver::analizarNaturaleza] Fallback a "indeterminado".', [
                'error' => $e->getMessage(),
            ]);
            return 'indeterminado';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $texto Texto a evaluar.
     * @return float         Score de sesgo en el rango [0.0, 1.0].
     */
    public function detectarSesgoLenguaje(string $texto): float
    {
        $sistema = 'Eres un detector de sesgo lingüístico especializado en contenido periodístico. Analiza el texto y calcula un score de sesgo considerando: uso de lenguaje emocional, sensacionalismo, amarillismo, parcialidad evidente y carga ideológica. Responde ÚNICAMENTE con un objeto JSON: {"score": 0.00} donde el valor es decimal entre 0.0 (sin sesgo) y 1.0 (sesgo máximo). Sin texto adicional.';

        try {
            $respuesta = $this->llamarApi($sistema, "Evalúa el sesgo del siguiente texto:\n\n{$texto}", 0.1);

            $limpio = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $json   = json_decode($limpio ?? $respuesta, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['score'])) {
                return (float) max(0.0, min(1.0, $json['score']));
            }

            if (preg_match('/(\d+(?:\.\d+)?)/', $respuesta, $matches)) {
                return (float) max(0.0, min(1.0, $matches[1]));
            }

            Log::warning('[GeminiDriver::detectarSesgoLenguaje] No se pudo parsear el score.', [
                'respuesta' => $respuesta,
            ]);
            return 0.0;
        } catch (\RuntimeException $e) {
            Log::warning('[GeminiDriver::detectarSesgoLenguaje] Fallback a 0.0.', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $texto Texto a verificar.
     * @return array{score: float, justificacion: string, motivo_breve?: string|null}
     */
    public function verificarCoherencia(string $texto): array
    {
        $fechaActual = now()->translatedFormat('d \d\e F \d\e Y');
        $fechaActualUpper = mb_strtoupper($fechaActual);
        $anioActual  = now()->year;

        $sistema = <<<PROMPT
Eres el motor de verificación factual de noticias (fact-checker) de DAGAZ en Colombia.
La fecha actual de referencia es estrictamente {$fechaActualUpper} (Año {$anioActual}).

REGLAS DE EVALUACIÓN:
1. Ten en cuenta que los comicios de octubre de 2026 (como las elecciones presidenciales de Brasil del 25 de octubre de 2026) YA OCURRIERON en el pasado y NO son eventos futuros. Tu entrenamiento interno puede tener fecha de corte anterior, por lo que DEBES DAR PRIORIDAD ABSOLUTA a la "EVIDENCIA DOCUMENTAL ACTUALIZADA EN LA BASE DE DATOS" y resultados web vigentes que acompañan la consulta.
2. Analiza si la afirmación es fáctica, exacta y consistente con los hechos, noticias y cotizaciones a fecha de {$fechaActual} (incluyendo actualidad económica volátil como dólar/TRM en Colombia, petróleo Brent/WTI, bolsa, inflación y decisiones gubernamentales recientes).
3. Si las fuentes verificadas o la realidad de mercado confirman o respaldan la afirmación, asigna un score entre 0.85 y 1.0 (VERDADERO). Si es parcialmente cierta o requiere matices, asigna entre 0.55 y 0.74 (PARCIAL). Si contradice la evidencia, inventa datos o es falsa, asigna entre 0.0 y 0.25 (FALSO).
4. Responde ÚNICAMENTE con un objeto JSON válido con las claves score, justificacion y motivo_breve. Conserva la justificación del análisis. En motivo_breve escribe una o dos frases cortas en español sobre el dato decisivo y la evidencia aportada. Nombra la fuente si aclara el hecho e incluye la fecha, el lugar o el contexto si son decisivos. Omite porcentajes de confianza, citas largas, URLs y categorías. Devuelve null en motivo_breve si la evidencia aportada no permite sostener tu conclusión.
PROMPT;

        $usuario = "Fecha de referencia: {$fechaActual}. Verifica fáctica y semánticamente la siguiente información contrastándola con la evidencia disponible:\n\n{$texto}";

        $default = ['score' => 0.0, 'justificacion' => 'No se pudo verificar la coherencia.'];

        try {
            $respuesta = $this->llamarApi($sistema, $usuario, 0.2);

            $limpio = trim((string) preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta));
            $json   = json_decode($limpio, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                $start = strpos($limpio, '{');
                $end   = strrpos($limpio, '}');
                if ($start !== false && $end !== false && $end > $start) {
                    $fragmento = substr($limpio, $start, $end - $start + 1);
                    $json      = json_decode($fragmento, true);
                    if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                        $sanitizado = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $fragmento);
                        $json       = json_decode($sanitizado ?? $fragmento, true);
                    }
                }
            }

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $jsonNorm = [];
                foreach ($json as $k => $v) {
                    $jsonNorm[trim((string) $k)] = $v;
                }

                $scoreRaw = $jsonNorm['score'] ?? $jsonNorm['puntuacion'] ?? $jsonNorm['confianza'] ?? null;
                $justRaw  = $jsonNorm['justificacion']
                    ?? $jsonNorm['justificación']
                    ?? $jsonNorm['explanation']
                    ?? $jsonNorm['razonamiento']
                    ?? $jsonNorm['resumen']
                    ?? null;

                if (is_numeric($scoreRaw) && is_string($justRaw) && trim($justRaw) !== '') {
                    $scoreFloat = (float) $scoreRaw;
                    if ($scoreFloat > 1.0 && $scoreFloat <= 100.0) {
                        $scoreFloat = $scoreFloat / 100.0;
                    }

                    return [
                        'score'         => (float) max(0.0, min(1.0, $scoreFloat)),
                        'justificacion' => trim($justRaw),
                        'motivo_breve'  => is_string($jsonNorm['motivo_breve'] ?? null) ? $jsonNorm['motivo_breve'] : null,
                    ];
                }
            }

            Log::warning('[GeminiDriver::verificarCoherencia] No se pudo parsear el JSON.', [
                'respuesta' => $respuesta,
            ]);
            return $default;
        } catch (\RuntimeException $e) {
            Log::warning('[GeminiDriver::verificarCoherencia] Fallback a default.', [
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $consulta Prompt o consulta en lenguaje natural.
     * @return string           Respuesta generada por el modelo.
     */
    public function interpretarPrompt(string $consulta): string
    {
        $sistema = 'Eres un asistente de análisis de información del sistema DAGAZ. Responde de forma precisa, objetiva y concisa.';

        try {
            return $this->llamarApi($sistema, $consulta, 0.3);
        } catch (\RuntimeException $e) {
            Log::warning('[GeminiDriver::interpretarPrompt] Error al interpretar prompt.', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $texto Texto con afirmaciones compuestas.
     * @return array<int, string> Lista de afirmaciones atómicas.
     */
    public function descomponerAfirmaciones(string $texto): array
    {
        $sistema = 'Eres un experto en fact-checking. Descompón el texto en afirmaciones atómicas: unidades mínimas de información verificables de forma independiente. Cada afirmación debe ser una oración completa y autocontenida. Responde ÚNICAMENTE con un array JSON: ["afirmación 1", "afirmación 2", ...]. Sin texto adicional.';

        try {
            $respuesta = $this->llamarApi($sistema, "Descompone en afirmaciones atómicas:\n\n{$texto}", 0.1);

            $limpio = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $array  = json_decode(trim($limpio ?? $respuesta), true);

            if ((json_last_error() !== JSON_ERROR_NONE || !is_array($array)) && preg_match('/\[.*\]/s', $respuesta, $matches)) {
                $array = json_decode($matches[0], true);
            }

            if (json_last_error() === JSON_ERROR_NONE && is_array($array)) {
                return array_values(array_filter($array, 'is_string'));
            }

            Log::warning('[GeminiDriver::descomponerAfirmaciones] No se pudo parsear el array JSON.', [
                'respuesta' => $respuesta,
            ]);
            return [];
        } catch (\RuntimeException $e) {
            Log::warning('[GeminiDriver::descomponerAfirmaciones] Fallback a array vacío.', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string Retorna 'Gemini'.
     */
    public function getNombreProveedor(): string
    {
        return 'Gemini';
    }
}
