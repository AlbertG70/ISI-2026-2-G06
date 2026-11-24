<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Contracts\AdaptadorApiIaInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

/**
 * Class OpenAiDriver
 *
 * Adaptador concreto para la API de OpenAI (GPT-4o y variantes).
 *
 * Implementa el contrato AdaptadorApiIaInterface siguiendo el patrón
 * Strategy/Adapter: el núcleo del dominio DAGAZ no sabe qué proveedor
 * de IA se está usando; sólo conoce la interfaz.
 *
 * Configuración esperada:
 *  - $apiKey   : Clave de API de OpenAI (env OPENAI_API_KEY).
 *  - $modelo   : Modelo a usar, por defecto 'gpt-4o'.
 *  - $maxTokens: Límite de tokens en la respuesta.
 *
 * @package App\Adapters\Ia
 * @version 1.0.0
 */
class OpenAiDriver implements AdaptadorApiIaInterface
{
    /**
     * Endpoint de la API de completions (OpenAI u OpenRouter).
     *
     * @var string
     */
    private string $endpoint;

    /**
     * Clave de autenticación de la API.
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Identificador del modelo de lenguaje a utilizar.
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
     * Nombre descriptivo del proveedor activo ('OpenAI' u 'OpenRouter').
     *
     * @var string
     */
    private string $nombreProveedor;

    /**
     * Crea una nueva instancia del driver de OpenAI / OpenRouter.
     *
     * @param string $apiKey          Clave de API de OpenAI u OpenRouter.
     * @param string $modelo          Modelo a usar (por defecto 'gpt-4o').
     * @param int    $maxTokens       Límite de tokens en la respuesta (por defecto 2000).
     * @param string $endpoint        URL de completions (OpenAI u OpenRouter).
     * @param string $nombreProveedor Nombre del proveedor para telemetría.
     */
    public function __construct(
        string $apiKey,
        string $modelo = 'gpt-4o',
        int $maxTokens = 2000,
        string $endpoint = 'https://api.openai.com/v1/chat/completions',
        string $nombreProveedor = 'OpenAI'
    ) {
        $this->apiKey          = $apiKey;
        $this->modelo          = $modelo;
        $this->maxTokens       = $maxTokens;
        $this->endpoint        = $endpoint;
        $this->nombreProveedor = $nombreProveedor;

        if (str_starts_with($apiKey, 'sk-or-') && $this->endpoint === 'https://api.openai.com/v1/chat/completions') {
            $this->endpoint        = 'https://openrouter.ai/api/v1/chat/completions';
            $this->nombreProveedor = 'OpenRouter';
            if ($this->modelo === 'gpt-4o') {
                $this->modelo = 'openai/gpt-4o-mini';
            }
        }
    }

    /**
     * Realiza una llamada HTTP a la API de OpenAI u OpenRouter con el array de mensajes dado.
     *
     * @param  array<int, array{role: string, content: string}> $mensajes   Historial de mensajes.
     * @param  float                                            $temperatura Creatividad del modelo [0.0, 1.0].
     * @return string Texto generado por el modelo.
     *
     * @throws \RuntimeException Si la API retorna un error o la respuesta es inesperada.
     */
    private function llamarApi(array $mensajes, float $temperatura = 0.2): string
    {
        try {
            $esOpenRouter = str_contains($this->endpoint, 'openrouter.ai');
            $headers = [];
            if ($esOpenRouter) {
                $headers['HTTP-Referer'] = config('app.url', 'http://localhost:8000');
                $headers['X-Title']      = 'DAGAZ Verificador de Noticias';
            }

            $http = Http::withToken($this->apiKey)
                ->withHeaders($headers)
                ->timeout(25);

            if (app()->environment('local', 'testing') || DIRECTORY_SEPARATOR === '\\') {
                $http = $http->withoutVerifying();
            }

            $payload = [
                'model'       => $this->modelo,
                'messages'    => $mensajes,
                'max_tokens'  => $this->maxTokens,
                'temperature' => $temperatura,
            ];

            $response = $http->post($this->endpoint, $payload);

            $response->throw();

            /** @var array $body */
            $body = $response->json();

            $mensaje   = $body['choices'][0]['message'] ?? [];
            $contenido = trim((string) ($mensaje['content'] ?? ''));

            if ($contenido === '' && !empty($mensaje['reasoning'])) {
                $contenido = trim((string) $mensaje['reasoning']);
            }

            // Remover bloques de razonamiento CoT (<think>...</think>) si el modelo los incluyó en el contenido
            if (str_contains($contenido, '</think>')) {
                $partes    = explode('</think>', $contenido);
                $contenido = trim((string) end($partes));
            } elseif (str_starts_with($contenido, '<think>')) {
                $contenido = trim((string) preg_replace('/^<think>.*?$/s', '', $contenido));
            }

            return $contenido;
        } catch (RequestException $e) {
            Log::error('[OpenAiDriver] Error en la llamada a la API (' . $this->nombreProveedor . ').', [
                'mensaje'   => $e->getMessage(),
                'status'    => $e->response?->status(),
                'respuesta' => $e->response?->body(),
            ]);
            throw new \RuntimeException(
                'Error al comunicarse con la API de OpenAI: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error('[OpenAiDriver] Error inesperado al llamar a OpenAI.', [
                'mensaje' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Error inesperado en OpenAiDriver: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
     *
     * Clasifica el texto en categorías como: noticia, opinión, sátira,
     * publicidad, desinformación, contenido neutral, propaganda, etc.
     *
     * @param  string $texto Texto a analizar.
     * @return string        Naturaleza/tipo del contenido detectado.
     */
    public function analizarNaturaleza(string $texto): string
    {
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un analizador experto de contenido mediático. Clasifica el texto recibido en UNA de las siguientes categorías: noticia, opinión, sátira, publicidad, desinformación, propaganda, contenido neutral, otro. Responde ÚNICAMENTE con el nombre de la categoría, en minúsculas y sin puntuación adicional.',
            ],
            [
                'role'    => 'user',
                'content' => "Clasifica el siguiente texto:\n\n{$texto}",
            ],
        ];

        try {
            return $this->llamarApi($mensajes, 0.1);
        } catch (\RuntimeException $e) {
            Log::warning('[OpenAiDriver::analizarNaturaleza] Fallback a "indeterminado".', [
                'error' => $e->getMessage(),
            ]);
            return 'indeterminado';
        }
    }

    /**
     * {@inheritdoc}
     *
     * Solicita al LLM que evalúe el nivel de sesgo, sensacionalismo o
     * amarillismo del texto y que retorne exclusivamente un número JSON
     * entre 0.0 y 1.0.
     *
     * @param  string $texto Texto a evaluar.
     * @return float         Score de sesgo en el rango [0.0, 1.0].
     */
    public function detectarSesgoLenguaje(string $texto): float
    {
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un detector de sesgo lingüístico especializado en contenido periodístico. Analiza el texto proporcionado y calcula un score de sesgo que incluya: uso de lenguaje emocional, sensacionalismo, amarillismo, parcialidad evidente y carga ideológica. Responde ÚNICAMENTE con un objeto JSON con el formato: {"score": 0.00} donde el valor es un número decimal entre 0.0 (sin sesgo) y 1.0 (sesgo máximo). Sin explicaciones adicionales.',
            ],
            [
                'role'    => 'user',
                'content' => "Evalúa el sesgo del siguiente texto:\n\n{$texto}",
            ],
        ];

        try {
            $respuesta = $this->llamarApi($mensajes, 0.1);
            $limpio    = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $json      = json_decode(trim($limpio ?? $respuesta), true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['score'])) {
                return (float) max(0.0, min(1.0, $json['score']));
            }

            // Intento de extracción con regex si el JSON viene con ruido
            if (preg_match('/(\d+(?:\.\d+)?)/', $respuesta, $matches)) {
                return (float) max(0.0, min(1.0, $matches[1]));
            }

            Log::warning('[OpenAiDriver::detectarSesgoLenguaje] No se pudo parsear el score.', [
                'respuesta' => $respuesta,
            ]);
            return 0.0;
        } catch (\RuntimeException $e) {
            Log::warning('[OpenAiDriver::detectarSesgoLenguaje] Fallback a 0.0.', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Solicita al LLM que evalúe la coherencia semántica interna del texto
     * y retorne un JSON con score y justificación.
     *
     * @param  string $texto Texto a verificar.
     * @return array{score: float, justificacion: string, motivo_breve?: string|null}
     */
    public function verificarCoherencia(string $texto): array
    {
        $fechaActual = now()->translatedFormat('F \d\e Y');
        $fechaActualUpper = mb_strtoupper($fechaActual);

        $mensajes = [
            [
                'role'    => 'system',
                'content' => "Eres el motor de verificación factual de noticias (fact-checker) de DAGAZ en Colombia. La fecha actual es {$fechaActualUpper} de " . now()->year . ". Ten en cuenta que los comicios de octubre de 2026 (como las elecciones presidenciales de Brasil del 25 de octubre de 2026) YA OCURRIERON en el pasado y NO son eventos futuros. Para temas de actualidad volátil (precio del dólar/TRM, petróleo Brent/WTI, acciones, inflación o política del día), evalúa la veracidad conforme a la realidad a fecha actual. Tu entrenamiento interno puede tener fecha de corte anterior, por lo que DEBES DAR PRIORIDAD ABSOLUTA a la \"EVIDENCIA DOCUMENTAL ACTUALIZADA EN LA BASE DE DATOS Y/O BÚSQUEDA WEB\" que acompaña la consulta. Si las fuentes y la realidad verificada confirman la afirmación, asigna un score entre 0.85 y 1.0 (VERDADERO). Si es parcialmente cierta o requiere matices, asigna entre 0.55 y 0.74. Si contradice la evidencia o es falsa, asigna entre 0.0 y 0.20. Responde ÚNICAMENTE con un objeto JSON válido con las claves score, justificacion y motivo_breve. Conserva la justificación del análisis. En motivo_breve escribe una o dos frases cortas en español sobre el dato decisivo y la evidencia aportada. Nombra la fuente si aclara el hecho e incluye la fecha, el lugar o el contexto si son decisivos. Omite porcentajes de confianza, citas largas, URLs y categorías. Devuelve null en motivo_breve si la evidencia aportada no permite sostener tu conclusión.",
            ],
            [
                'role'    => 'user',
                'content' => "Fecha de referencia: {$fechaActual}. Verifica la siguiente información contrastándola con la evidencia disponible:\n\n{$texto}",
            ],
        ];

        $default = ['score' => 0.0, 'justificacion' => 'No se pudo verificar la coherencia.'];

        try {
            $respuesta = $this->llamarApi($mensajes, 0.2);

            // Extraer JSON aunque venga envuelto en markdown code block o texto adicional
            $limpio = trim((string) preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta));
            $json   = json_decode($limpio, true);

            if ((json_last_error() !== JSON_ERROR_NONE || !is_array($json))) {
                $start = strpos($limpio, '{');
                $end   = strrpos($limpio, '}');
                if ($start !== false && $end !== false && $end > $start) {
                    $json = json_decode(substr($limpio, $start, $end - $start + 1), true);
                }
            }

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                $scoreRaw = $json['score'] ?? $json['puntuacion'] ?? $json['confianza'] ?? null;
                $justRaw  = $json['justificacion']
                    ?? $json['justificación']
                    ?? $json['explanation']
                    ?? $json['razonamiento']
                    ?? $json['resumen']
                    ?? null;

                if (is_numeric($scoreRaw) && is_string($justRaw) && trim($justRaw) !== '') {
                    $scoreFloat = (float) $scoreRaw;
                    if ($scoreFloat > 1.0 && $scoreFloat <= 100.0) {
                        $scoreFloat = $scoreFloat / 100.0;
                    }

                    return [
                        'score'         => (float) max(0.0, min(1.0, $scoreFloat)),
                        'justificacion' => trim($justRaw),
                        'motivo_breve'  => is_string($json['motivo_breve'] ?? null) ? $json['motivo_breve'] : null,
                    ];
                }
            }

            Log::warning('[OpenAiDriver::verificarCoherencia] No se pudo parsear el JSON.', [
                'respuesta' => $respuesta,
            ]);
            return $default;
        } catch (\RuntimeException $e) {
            Log::warning('[OpenAiDriver::verificarCoherencia] Fallback a default.', [
                'error' => $e->getMessage(),
            ]);
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     *
     * Envía el prompt directamente al LLM sin sistema especializado y
     * retorna la respuesta en bruto.
     *
     * @param  string $consulta Prompt o consulta en lenguaje natural.
     * @return string           Respuesta generada por el modelo.
     */
    public function interpretarPrompt(string $consulta): string
    {
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un asistente de análisis de información del sistema DAGAZ. Responde de forma precisa, objetiva y concisa.',
            ],
            [
                'role'    => 'user',
                'content' => $consulta,
            ],
        ];

        try {
            return $this->llamarApi($mensajes, 0.3);
        } catch (\RuntimeException $e) {
            Log::warning('[OpenAiDriver::interpretarPrompt] Error al interpretar prompt.', [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * {@inheritdoc}
     *
     * Instruye al LLM para que descomponga el texto en afirmaciones atómicas
     * verificables de forma independiente y retorne un array JSON.
     *
     * @param  string $texto Texto con afirmaciones compuestas.
     * @return array<int, string> Lista de afirmaciones atómicas.
     */
    public function descomponerAfirmaciones(string $texto): array
    {
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un experto en fact-checking y análisis de afirmaciones. Tu tarea es descomponer el texto en afirmaciones atómicas: unidades mínimas de información que puedan verificarse de forma independiente. Cada afirmación debe ser una oración completa y autocontenida. Responde ÚNICAMENTE con un array JSON: ["afirmación 1", "afirmación 2", ...]. Sin texto adicional.',
            ],
            [
                'role'    => 'user',
                'content' => "Descompone en afirmaciones atómicas el siguiente texto:\n\n{$texto}",
            ],
        ];

        try {
            $respuesta = $this->llamarApi($mensajes, 0.1);

            // Limpiar posible markdown code block o texto adicional
            $limpio = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $array  = json_decode($limpio ?? $respuesta, true);

            if ((json_last_error() !== JSON_ERROR_NONE || !is_array($array)) && preg_match('/\[.*\]/s', $respuesta, $matches)) {
                $array = json_decode($matches[0], true);
            }

            if (json_last_error() === JSON_ERROR_NONE && is_array($array)) {
                return array_values(array_filter($array, 'is_string'));
            }

            Log::warning('[OpenAiDriver::descomponerAfirmaciones] No se pudo parsear el array JSON.', [
                'respuesta' => $respuesta,
            ]);
            return [];
        } catch (\RuntimeException $e) {
            Log::warning('[OpenAiDriver::descomponerAfirmaciones] Fallback a array vacío.', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string Retorna 'OpenAI'.
     */
    public function getNombreProveedor(): string
    {
        return $this->nombreProveedor;
    }
}
