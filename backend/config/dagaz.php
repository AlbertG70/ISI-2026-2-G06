<?php

/**
 * Configuración principal del sistema DAGAZ.
 *
 * Centraliza todos los parámetros del verificador de noticias.
 * Los valores de tipos/estados DEBEN coincidir exactamente con
 * los CHECK constraints de la BD raiden (MariaDB 10.4.32 / XAMPP).
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Metadatos de Despliegue y HTTPS
    |--------------------------------------------------------------------------
    */
    'version'     => env('DAGAZ_VERSION', '0.0.24'),
    'force_https' => env('APP_FORCE_HTTPS') !== null
        ? filter_var(env('APP_FORCE_HTTPS'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : null,

    /*
    |--------------------------------------------------------------------------
    | Proveedor de IA activo
    |--------------------------------------------------------------------------
    | Valores soportados: "openai", "openrouter", "gemini", "local"
    */
    'ia_proveedor' => env('DAGAZ_IA_PROVEEDOR', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Configuración OpenAI
    |--------------------------------------------------------------------------
    */
    'openai' => [
        'api_key'    => env('OPENAI_API_KEY', ''),
        'modelo'     => env('OPENAI_MODELO', env('OPENAI_MODEL', 'gpt-4o')),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 2000),
        'endpoint'   => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración OpenRouter (compatible con API OpenAI)
    |--------------------------------------------------------------------------
    */
    'openrouter' => [
        'api_key'    => env('OPENROUTER_API_KEY', env('OPENAI_API_KEY', '')),
        'modelo'     => env('OPENROUTER_MODELO', env('OPENROUTER_MODEL', 'openai/gpt-4o-mini')),
        'max_tokens' => (int) env('OPENROUTER_MAX_TOKENS', 2000),
        'endpoint'   => env('OPENROUTER_ENDPOINT', 'https://openrouter.ai/api/v1/chat/completions'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración Gemini (Google)
    |--------------------------------------------------------------------------
    */
    'gemini' => [
        'api_key'          => env('GEMINI_API_KEY', ''),
        'modelo'           => env('GEMINI_MODELO', env('GEMINI_MODEL', 'gemini-3.6-flash')),
        'max_tokens'       => (int) env('GEMINI_MAX_TOKENS', 2500),
        'fallback_modelos' => [
            env('GEMINI_COUNCIL_MODEL_1', 'gemini-3-flash-preview'),
            env('GEMINI_COUNCIL_MODEL_2', 'gemini-3.5-flash'),
            env('GEMINI_COUNCIL_MODEL_3', 'gemini-3.5-flash-lite'),
            env('GEMINI_COUNCIL_MODEL_4', 'gemini-flash-lite-latest'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración Anthropic (Claude)
    |--------------------------------------------------------------------------
    */
    'anthropic' => [
        'api_key'    => env('ANTHROPIC_API_KEY', ''),
        'modelo'     => env('ANTHROPIC_MODELO', 'claude-3-5-sonnet-20241022'),
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Configuración LLM Local (Ollama / vLLM)
    |--------------------------------------------------------------------------
    */
    'local' => [
        'endpoint'   => env('OLLAMA_ENDPOINT', 'http://localhost:11434/api/chat'),
        'modelo'     => env('OLLAMA_MODEL', 'llama3.2'),
        'max_tokens' => (int) env('OLLAMA_MAX_TOKENS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Consejo de Verificación Multi-Modelo (Harness Multi-Agente)
    |--------------------------------------------------------------------------
    | Ejecuta en paralelo la validación del veredicto con ChatGPT, Claude,
    | Gemini, DeepSeek-R1 y Qwen.
    */
    'multi_modelo' => [
        'habilitado'         => filter_var(env('DAGAZ_MULTI_MODELO_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'timeout_segundos'   => (int) env('DAGAZ_MULTI_MODELO_TIMEOUT', 20),
        'calibrar_veredicto' => filter_var(env('DAGAZ_MULTI_MODELO_CALIBRAR', true), FILTER_VALIDATE_BOOLEAN),
        'qwen_activo'        => filter_var(env('DAGAZ_COUNCIL_QWEN_ACTIVO', false), FILTER_VALIDATE_BOOLEAN),
        'openrouter_modelos' => [
            'agente_1' => env('OPENROUTER_COUNCIL_MODEL_1', 'cohere/north-mini-code:free'),
            'agente_2' => env('OPENROUTER_COUNCIL_MODEL_2', 'recursive/flint-v1.0:free'),
            'agente_3' => env('OPENROUTER_COUNCIL_MODEL_3', 'dots-studio/dots-3-note-preview:free'),
            'agente_4' => env('OPENROUTER_COUNCIL_MODEL_4', 'openrouter/free'),
            'agente_5' => env('OPENROUTER_COUNCIL_MODEL_5', 'openrouter/free'),
        ],
        'gemini_modelos' => [
            'agente_1' => env('GEMINI_COUNCIL_MODEL_1', 'gemini-3-flash-preview'),
            'agente_2' => env('GEMINI_COUNCIL_MODEL_2', 'gemini-3.5-flash'),
            'agente_3' => env('GEMINI_COUNCIL_MODEL_3', 'gemini-3.5-flash-lite'),
            'agente_4' => env('GEMINI_COUNCIL_MODEL_4', 'gemini-flash-lite-latest'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Microservicios Python (OCR / STT)
    |--------------------------------------------------------------------------
    */
    'ocr_service_url' => env('OCR_SERVICE_URL', 'http://localhost:5001/ocr'),
    'stt_service_url' => env('STT_SERVICE_URL', 'http://localhost:5002/stt'),

    /*
    |--------------------------------------------------------------------------
    | Conector Archive.org / Wayback Machine (TSK-3.2 & REQ-10)
    |--------------------------------------------------------------------------
    */
    'archive_org' => [
        'base_url'                         => env('ARCHIVE_ORG_BASE_URL', 'https://archive.org/wayback/available'),
        'cdx_url'                          => env('ARCHIVE_ORG_CDX_URL', 'https://web.archive.org/cdx/search/cdx'),
        'timeout'                          => (int) env('ARCHIVE_ORG_TIMEOUT', 15),
        'umbral_descontextualizacion_dias' => (int) env('ARCHIVE_ORG_UMBRAL_DIAS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker de Costes
    |--------------------------------------------------------------------------
    */
    'max_tokens_por_hora'    => (int)   env('DAGAZ_MAX_TOKENS_POR_HORA', 50000),
    'presupuesto_diario_usd' => (float) env('DAGAZ_PRESUPUESTO_DIARIO_USD', 5.00),

    /*
    |--------------------------------------------------------------------------
    | Umbrales de Veredicto (0–100)
    |--------------------------------------------------------------------------
    | score >= 75           → VERDADERO
    | score >= 55           → PARCIAL
    | score >= 30           → ENGAÑOSO
    | score >= 10           → FALSO
    | score <  10           → NO_VERIFICABLE
    */
    'umbral_verdadero'      => 75.00,
    'umbral_parcial'        => 55.00,
    'umbral_enganoso'       => 30.00,
    'umbral_falso'          => 10.00,

    /*
    |--------------------------------------------------------------------------
    | Roles del sistema
    |--------------------------------------------------------------------------
    | Coincide con los valores aceptados en la columna `rol` de `usuarios`.
    | Default en BD: 'lector'
    */
    'roles' => ['lector', 'adulto', 'editor', 'admin'],

    /*
    |--------------------------------------------------------------------------
    | Tipos de entrada — CHECK constraint de `contenidos.tipo_entrada`
    |--------------------------------------------------------------------------
    | CHECK (`tipo_entrada` IN ('TEXTO','URL','IMAGEN_OCR','RED_SOCIAL'))
    */
    'tipos_entrada' => ['TEXTO', 'URL', 'IMAGEN_OCR', 'RED_SOCIAL'],

    /*
    |--------------------------------------------------------------------------
    | Estados de contenido — CHECK constraint de `contenidos.estado`
    |--------------------------------------------------------------------------
    | CHECK (`estado` IN ('PENDIENTE','PROCESANDO','VERIFICADO','ERROR'))
    */
    'estados_contenido' => ['PENDIENTE', 'PROCESANDO', 'VERIFICADO', 'ERROR'],

    /*
    |--------------------------------------------------------------------------
    | Tipos de veredicto — CHECK constraint de `veredictos.tipo_veredicto`
    |--------------------------------------------------------------------------
    | CHECK (`tipo_veredicto` IN ('VERDADERO','FALSO','ENGAÑOSO','PARCIAL','NO_VERIFICABLE'))
    */
    'tipos_veredicto' => ['VERDADERO', 'FALSO', 'ENGAÑOSO', 'PARCIAL', 'NO_VERIFICABLE'],

    /*
    |--------------------------------------------------------------------------
    | Tipos de fuente — CHECK constraint de `fuentes.tipo_fuente`
    |--------------------------------------------------------------------------
    | CHECK (`tipo_fuente` IN ('OFICIAL','PERIODISTICO','ACADEMICO','RED_SOCIAL','OTRO'))
    */
    'tipos_fuente' => ['OFICIAL', 'PERIODISTICO', 'ACADEMICO', 'RED_SOCIAL', 'OTRO'],

    /*
    |--------------------------------------------------------------------------
    | Ponderación de fuentes por tipo
    |--------------------------------------------------------------------------
    | Escala 0.0–1.0 para el grafo de credibilidad.
    */
    'pesos_fuente' => [
        'OFICIAL'      => 1.0,
        'PERIODISTICO' => 0.80,
        'ACADEMICO'    => 0.90,
        'RED_SOCIAL'   => 0.40,
        'OTRO'         => 0.50,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redacciones Socias y API Keys (TSK-4.2 & REQ-16 / REQ-22 / REQ-26)
    |--------------------------------------------------------------------------
    | Lista de redacciones aliadas y salas de prensa autorizadas para acceder
    | a la API pública v1 de DAGAZ mediante la cabecera X-API-KEY.
    */
    'partners' => [
        'keys' => [
            env('DAGAZ_PARTNER_KEY_ESPECTADOR', 'dagaz_partner_elespectador_sec99a') => [
                'id_socio'          => 'socio-espectador',
                'nombre'            => 'El Espectador — Sala Digital',
                'email_contacto'    => 'redaccion@elespectador.com',
                'rate_limit_minute' => (int) env('DAGAZ_PARTNER_RATE_LIMIT', 60),
                'activo'            => true,
                'permisos'          => ['verificar', 'consultar', 'fuentes', 'auditoria'],
            ],
            env('DAGAZ_PARTNER_KEY_COLOMBIACHECK', 'dagaz_partner_colombiacheck_f73b12') => [
                'id_socio'          => 'socio-colombiacheck',
                'nombre'            => 'ColombiaCheck — Redacción Central',
                'email_contacto'    => 'contacto@colombiacheck.com',
                'rate_limit_minute' => (int) env('DAGAZ_PARTNER_RATE_LIMIT', 120),
                'activo'            => true,
                'permisos'          => ['verificar', 'consultar', 'fuentes', 'auditoria'],
            ],
            env('DAGAZ_PARTNER_KEY_SILLAVACIA', 'dagaz_partner_lasillavacia_d48c01') => [
                'id_socio'          => 'socio-lasillavacia',
                'nombre'            => 'La Silla Vacía — Detector de Mentiras',
                'email_contacto'    => 'detector@lasillavacia.com',
                'rate_limit_minute' => (int) env('DAGAZ_PARTNER_RATE_LIMIT', 90),
                'activo'            => true,
                'permisos'          => ['verificar', 'consultar', 'fuentes', 'auditoria'],
            ],
            env('DAGAZ_PARTNER_KEY_TEST', 'dagaz_partner_test_key_secret_12345') => [
                'id_socio'          => 'socio-test',
                'nombre'            => 'Redacción de Prueba Piloto',
                'email_contacto'    => 'piloto@dagaz.test',
                'rate_limit_minute' => 60,
                'activo'            => true,
                'permisos'          => ['verificar', 'consultar', 'fuentes', 'auditoria'],
            ],
        ],
    ],

];

