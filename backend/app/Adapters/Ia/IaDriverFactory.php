<?php

declare(strict_types=1);

namespace App\Adapters\Ia;

use App\Contracts\AdaptadorApiIaInterface;

/**
 * Class IaDriverFactory
 *
 * Fábrica de drivers de IA para el proyecto DAGAZ.
 *
 * Implementa el patrón Factory + Strategy:
 *  - **Factory**: centraliza la creación de adaptadores concretos.
 *  - **Strategy**: el caller recibe un AdaptadorApiIaInterface y no sabe
 *    qué proveedor está usando; puede cambiarse en caliente desde config.
 *
 * El proveedor activo se resuelve en el siguiente orden de prioridad:
 *  1. Argumento `$proveedor` pasado explícitamente al método `crear()`.
 *  2. Clave de configuración `dagaz.ia_proveedor` (config/dagaz.php).
 *  3. Fallback hardcoded: `'openai'`.
 *
 * Proveedores soportados:
 *  - `'openai'`  → {@see OpenAiDriver}  (requiere env OPENAI_API_KEY)
 *  - `'gemini'`  → {@see GeminiDriver}  (requiere env GEMINI_API_KEY)
 *  - `'local'`   → {@see LocalLlmDriver} (requiere Ollama corriendo localmente)
 *
 * @package App\Adapters\Ia
 * @version 1.0.0
 */
class IaDriverFactory
{
    /**
     * Crea y retorna el driver de IA correspondiente al proveedor indicado.
     *
     * Lee las credenciales y parámetros de configuración desde `config/dagaz.php`
     * o directamente desde variables de entorno como fallback.
     *
     * Ejemplo de uso:
     * ```php
     * // Usa el proveedor configurado en dagaz.ia_proveedor
     * $ia = IaDriverFactory::crear();
     *
     * // Fuerza un proveedor concreto
     * $ia = IaDriverFactory::crear('gemini');
     *
     * $naturaleza = $ia->analizarNaturaleza($texto);
     * ```
     *
     * @param  string|null $proveedor Identificador del proveedor: 'openai', 'gemini' o 'local'.
     *                                Si es null, se lee de config('dagaz.ia_proveedor').
     * @return AdaptadorApiIaInterface Driver instanciado y listo para usar.
     *
     * @throws \InvalidArgumentException Si el proveedor indicado no es conocido por la fábrica.
     */
    public static function crear(?string $proveedor = null): AdaptadorApiIaInterface
    {
        // Resolución del proveedor con cadena de fallback
        $proveedor = strtolower(
            $proveedor
            ?? (string) config('dagaz.ia_proveedor', 'openai')
        );

        return match ($proveedor) {
            'openai'     => self::crearOpenAi(),
            'openrouter' => self::crearOpenRouter(),
            'gemini'     => self::crearGemini(),
            'local'      => self::crearLocal(),
            default      => throw new \InvalidArgumentException(
                "Proveedor de IA desconocido: '{$proveedor}'. "
                . "Proveedores válidos: 'openai', 'openrouter', 'gemini', 'local'."
            ),
        };
    }

    /**
     * Instancia el driver de OpenAI con la configuración del entorno.
     * Si detecta una clave de OpenRouter (sk-or-) o OPENROUTER_API_KEY activa,
     * enruta automáticamente hacia el endpoint de OpenRouter.
     *
     * @return OpenAiDriver
     *
     * @throws \InvalidArgumentException Si ninguna API key está configurada.
     */
    private static function crearOpenAi(): OpenAiDriver
    {
        $apiKey = (string) config('dagaz.openai.api_key', '');

        if (empty($apiKey)) {
            $openRouterKey = (string) config('dagaz.openrouter.api_key', '');
            if (!empty($openRouterKey)) {
                return self::crearOpenRouter();
            }

            throw new \InvalidArgumentException(
                'La API key de OpenAI no está configurada. '
                . 'Define OPENAI_API_KEY o OPENROUTER_API_KEY en tu archivo .env.'
            );
        }

        if (str_starts_with($apiKey, 'sk-or-')) {
            return self::crearOpenRouter($apiKey);
        }

        return new OpenAiDriver(
            apiKey:    $apiKey,
            modelo:    (string) config('dagaz.openai.modelo', 'gpt-4o'),
            maxTokens: (int)    config('dagaz.openai.max_tokens', 2000),
            endpoint:  (string) config('dagaz.openai.endpoint', 'https://api.openai.com/v1/chat/completions'),
        );
    }

    /**
     * Instancia el driver para OpenRouter (API compatible con OpenAI).
     *
     * @param  string|null $apiKeyOverride Clave explícita si fue provista en OPENAI_API_KEY.
     * @return OpenAiDriver
     *
     * @throws \InvalidArgumentException Si la API key de OpenRouter está vacía.
     */
    private static function crearOpenRouter(?string $apiKeyOverride = null): OpenAiDriver
    {
        $apiKey = $apiKeyOverride
            ?? (string) config('dagaz.openrouter.api_key', '');

        if (empty($apiKey)) {
            throw new \InvalidArgumentException(
                'La API key de OpenRouter no está configurada. '
                . 'Define OPENROUTER_API_KEY en tu archivo .env.'
            );
        }

        return new OpenAiDriver(
            apiKey:          $apiKey,
            modelo:          (string) config('dagaz.openrouter.modelo', 'openai/gpt-4o-mini'),
            maxTokens:       (int)    config('dagaz.openrouter.max_tokens', 2000),
            endpoint:        (string) config('dagaz.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
            nombreProveedor: 'OpenRouter',
        );
    }

    /**
     * Instancia el driver de Google Gemini con la configuración del entorno.
     *
     * Lee:
     *  - `dagaz.gemini.api_key`   → GEMINI_API_KEY
     *  - `dagaz.gemini.modelo`    → GEMINI_MODEL        (default: 'gemini-2.0-flash')
     *  - `dagaz.gemini.max_tokens` → GEMINI_MAX_TOKENS  (default: 2000)
     *
     * @return GeminiDriver
     *
     * @throws \InvalidArgumentException Si GEMINI_API_KEY está vacía.
     */
    private static function crearGemini(): GeminiDriver
    {
        $apiKey = (string) config('dagaz.gemini.api_key', '');

        if (empty($apiKey)) {
            throw new \InvalidArgumentException(
                'La API key de Gemini no está configurada. '
                . 'Define GEMINI_API_KEY en tu archivo .env o en config/dagaz.php.'
            );
        }

        $fallbacks = app()->runningUnitTests()
            ? []
            : array_values(array_filter((array) config('dagaz.gemini.fallback_modelos', [])));

        return new GeminiDriver(
            apiKey:          trim($apiKey),
            modelo:          trim((string) config('dagaz.gemini.modelo', 'gemini-3.6-flash')) ?: 'gemini-3.6-flash',
            maxTokens:       (int) config('dagaz.gemini.max_tokens', 2500),
            fallbackModelos: $fallbacks,
        );
    }

    /**
     * Instancia el driver de LLM local (Ollama) con la configuración del entorno.
     *
     * Lee:
     *  - `dagaz.local.endpoint`   → OLLAMA_ENDPOINT   (default: 'http://localhost:11434/api/chat')
     *  - `dagaz.local.modelo`     → OLLAMA_MODEL       (default: 'llama3.2')
     *  - `dagaz.local.max_tokens` → OLLAMA_MAX_TOKENS  (default: 2000)
     *
     * @return LocalLlmDriver
     */
    private static function crearLocal(): LocalLlmDriver
    {
        return new LocalLlmDriver(
            endpoint:  (string) config('dagaz.local.endpoint', 'http://localhost:11434/api/chat'),
            modelo:    (string) config('dagaz.local.modelo', 'llama3.2'),
            maxTokens: (int)    config('dagaz.local.max_tokens', 2000),
        );
    }
}
