<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Contracts\AdaptadorApiIaInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

/**
 * Class LocalLlmDriver
 *
 * Adaptador concreto para modelos de lenguaje locales ejecutados mediante
 * Ollama (https://ollama.com).
 *
 * Implementa el contrato AdaptadorApiIaInterface de forma que el dominio
 * DAGAZ pueda funcionar sin conectividad externa, usando modelos como
 * llama3.2, mistral, phi3, etc.
 *
 * Formato de API: Ollama REST API (compatible OpenAI /api/chat).
 *
 * Configuración esperada (desde IaDriverFactory o env):
 *  - $endpoint  : URL del servidor Ollama (env OLLAMA_ENDPOINT).
 *  - $modelo    : Nombre del modelo cargado en Ollama (env OLLAMA_MODEL).
 *  - $maxTokens : Límite aproximado de tokens en la respuesta.
 *
 * @package App\Adapters\Ia
 * @version 1.0.0
 */
class LocalLlmDriver implements AdaptadorApiIaInterface
{
    /**
     * URL del endpoint de la API de Ollama.
     *
     * @var string
     */
    private string $endpoint;

    /**
     * Nombre del modelo cargado en el servidor Ollama.
     *
     * @var string
     */
    private string $modelo;

    /**
     * Número máximo de tokens permitidos en la respuesta.
     *
     * @var int
     */
    private int $maxTokens;

    /**
     * Crea una nueva instancia del driver para LLM local (Ollama).
     *
     * @param string $endpoint  URL del endpoint de Ollama (por defecto 'http://localhost:11434/api/chat').
     * @param string $modelo    Nombre del modelo (por defecto 'llama3.2').
     * @param int    $maxTokens Límite de tokens en la respuesta (por defecto 2000).
     */
    public function __construct(
        string $endpoint = 'http://localhost:11434/api/chat',
        string $modelo = 'llama3.2',
        int $maxTokens = 2000
    ) {
        $this->endpoint  = $endpoint;
        $this->modelo    = $modelo;
        $this->maxTokens = $maxTokens;
    }

    /**
     * Realiza una llamada HTTP al servidor Ollama con el array de mensajes.
     *
     * Usa el formato de mensajes compatible con el endpoint /api/chat de Ollama,
     * que acepta roles 'system', 'user' y 'assistant'. La respuesta se obtiene
     * en modo no-streaming (stream: false).
     *
     * @param  array<int, array{role: string, content: string}> $mensajes   Historial de mensajes.
     * @param  float                                            $temperatura Creatividad del modelo [0.0, 1.0].
     * @return string Texto generado por el modelo local.
     *
     * @throws \RuntimeException Si el servidor Ollama no responde o retorna error.
     */
    private function llamarApi(array $mensajes, float $temperatura = 0.2): string
    {
        $payload = [
            'model'    => $this->modelo,
            'messages' => $mensajes,
            'stream'   => false,
            'options'  => [
                'temperature' => $temperatura,
                'num_predict' => $this->maxTokens,
            ],
        ];

        try {
            $response = Http::withHeaders(['Content-Type' => 'application/json'])
                ->timeout(120) // LLMs locales pueden ser lentos
                ->post($this->endpoint, $payload);

            $response->throw();

            /** @var array $body */
            $body = $response->json();

            return trim($body['message']['content'] ?? '');
        } catch (RequestException $e) {
            Log::error('[LocalLlmDriver] Error al conectar con Ollama.', [
                'endpoint'  => $this->endpoint,
                'modelo'    => $this->modelo,
                'mensaje'   => $e->getMessage(),
                'status'    => $e->response?->status(),
                'respuesta' => $e->response?->body(),
            ]);
            throw new \RuntimeException(
                'Error al comunicarse con Ollama en ' . $this->endpoint . ': ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        } catch (\Throwable $e) {
            Log::error('[LocalLlmDriver] Error inesperado al llamar a Ollama.', [
                'mensaje' => $e->getMessage(),
            ]);
            throw new \RuntimeException(
                'Error inesperado en LocalLlmDriver: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * {@inheritdoc}
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
            Log::warning('[LocalLlmDriver::analizarNaturaleza] Fallback a "indeterminado".', [
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
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un detector de sesgo lingüístico especializado en contenido periodístico. Analiza el texto y calcula un score de sesgo considerando: uso de lenguaje emocional, sensacionalismo, amarillismo, parcialidad evidente y carga ideológica. Responde ÚNICAMENTE con un objeto JSON: {"score": 0.00} donde el valor es decimal entre 0.0 (sin sesgo) y 1.0 (sesgo máximo). Sin texto adicional.',
            ],
            [
                'role'    => 'user',
                'content' => "Evalúa el sesgo del siguiente texto:\n\n{$texto}",
            ],
        ];

        try {
            $respuesta = $this->llamarApi($mensajes, 0.1);

            $limpio = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $json   = json_decode($limpio ?? $respuesta, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['score'])) {
                return (float) max(0.0, min(1.0, $json['score']));
            }

            if (preg_match('/(\d+(?:\.\d+)?)/', $respuesta, $matches)) {
                return (float) max(0.0, min(1.0, $matches[1]));
            }

            Log::warning('[LocalLlmDriver::detectarSesgoLenguaje] No se pudo parsear el score.', [
                'respuesta' => $respuesta,
            ]);
            return 0.0;
        } catch (\RuntimeException $e) {
            Log::warning('[LocalLlmDriver::detectarSesgoLenguaje] Fallback a 0.0.', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }

    /**
     * {@inheritdoc}
     *
     * @param  string $texto Texto a verificar.
     * @return array{score: float, justificacion: string}
     */
    public function verificarCoherencia(string $texto): array
    {
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un experto en lingüística computacional. Evalúa si el texto es semánticamente coherente: las afirmaciones no se contradicen, el argumento es lógicamente sólido y el flujo narrativo tiene sentido. Responde ÚNICAMENTE con JSON: {"score": 0.00, "justificacion": "texto breve"}. El score va de 0.0 (incoherente) a 1.0 (coherente).',
            ],
            [
                'role'    => 'user',
                'content' => "Verifica la coherencia del siguiente texto:\n\n{$texto}",
            ],
        ];

        $default = ['score' => 0.0, 'justificacion' => 'No se pudo verificar la coherencia.'];

        try {
            $respuesta = $this->llamarApi($mensajes, 0.2);

            $limpio = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $json   = json_decode($limpio ?? $respuesta, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($json['score'], $json['justificacion'])) {
                return [
                    'score'        => (float) max(0.0, min(1.0, $json['score'])),
                    'justificacion' => (string) $json['justificacion'],
                ];
            }

            Log::warning('[LocalLlmDriver::verificarCoherencia] No se pudo parsear el JSON.', [
                'respuesta' => $respuesta,
            ]);
            return $default;
        } catch (\RuntimeException $e) {
            Log::warning('[LocalLlmDriver::verificarCoherencia] Fallback a default.', [
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
            Log::warning('[LocalLlmDriver::interpretarPrompt] Error al interpretar prompt.', [
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
        $mensajes = [
            [
                'role'    => 'system',
                'content' => 'Eres un experto en fact-checking. Descompón el texto en afirmaciones atómicas: unidades mínimas de información verificables de forma independiente. Cada afirmación debe ser una oración completa y autocontenida. Responde ÚNICAMENTE con un array JSON: ["afirmación 1", "afirmación 2", ...]. Sin texto adicional.',
            ],
            [
                'role'    => 'user',
                'content' => "Descompone en afirmaciones atómicas:\n\n{$texto}",
            ],
        ];

        try {
            $respuesta = $this->llamarApi($mensajes, 0.1);

            $limpio = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $respuesta);
            $array  = json_decode($limpio ?? $respuesta, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($array)) {
                return array_values(array_filter($array, 'is_string'));
            }

            Log::warning('[LocalLlmDriver::descomponerAfirmaciones] No se pudo parsear el array JSON.', [
                'respuesta' => $respuesta,
            ]);
            return [];
        } catch (\RuntimeException $e) {
            Log::warning('[LocalLlmDriver::descomponerAfirmaciones] Fallback a array vacío.', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * {@inheritdoc}
     *
     * @return string Retorna 'Local (Ollama)'.
     */
    public function getNombreProveedor(): string
    {
        return 'Local (Ollama)';
    }
}
