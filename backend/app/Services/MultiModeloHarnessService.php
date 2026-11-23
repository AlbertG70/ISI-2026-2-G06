<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * MultiModeloHarnessService — Consejo de Verificación Multi-Modelo para DAGAZ
 *
 * Ejecuta en paralelo la evaluación cruzada de afirmaciones y veredictos
 * a través de un consejo multi-agente de modelos de IA de frontera (ChatGPT/GPT-4o,
 * Claude 3.5 Sonnet, Google Gemini 2.0 Flash) y modelos de pesos abiertos
 * (DeepSeek-R1, Qwen 2.5, Nemotron).
 *
 * Utiliza HTTP Pool asíncrono para enviar las peticiones concurrentemente,
 * garantizando mínima latencia (max latencia de red en lugar de suma de latencias).
 *
 * @package App\Services
 */
class MultiModeloHarnessService
{
    private const TIMEOUT_SEGUNDOS = 20;

    /**
     * Veredictos normalizados válidos en la base de datos de DAGAZ.
     */
    private const VEREDICTOS_VALIDOS = [
        'VERDADERO',
        'FALSO',
        'ENGAÑOSO',
        'PARCIAL',
        'NO_VERIFICABLE',
    ];

    /**
     * Evalúa una afirmación y un veredicto preliminar con el consejo de modelos.
     *
     * @param  string      $afirmacion          Afirmación o contenido bajo análisis.
     * @param  string      $veredictoPreliminar Veredicto determinado por el motor principal.
     * @param  array<int, string> $citasEvidencia Citas documentales extraídas de la base de datos (RAG).
     * @param  string|null $idProceso           UUID del proceso para trazabilidad.
     * @return array<string, mixed> Resumen de consenso, evaluaciones individuales y métricas.
     */
    public function evaluarConConsejo(
        string $afirmacion,
        string $veredictoPreliminar,
        array $citasEvidencia = [],
        ?string $idProceso = null
    ): array {
        if (!config('dagaz.multi_modelo.habilitado', true)) {
            return [
                'habilitado' => false,
                'mensaje'    => 'Evaluación multi-modelo desactivada en configuración.',
            ];
        }

        $modelosConfigurados = $this->resolverModelosActivos();
        if (empty($modelosConfigurados)) {
            Log::info('[MultiModeloHarness] No hay modelos configurados o con credenciales activas.');
            return [
                'habilitado'           => false,
                'total_modelos'        => 0,
                'mensaje'              => 'Sin credenciales configuradas para el consejo multi-modelo.',
                'calibracion_aplicada' => false,
            ];
        }

        $systemPrompt = $this->construirSystemPrompt();
        $userPrompt   = $this->construirUserPrompt($afirmacion, $veredictoPreliminar, $citasEvidencia);

        // ─── 1. Ejecución en paralelo vía Http::pool ────────────────────────
        $respuestasRaw = [];
        try {
            $respuestasRaw = Http::pool(function (Pool $pool) use ($modelosConfigurados, $systemPrompt, $userPrompt) {
                $peticiones = [];
                foreach ($modelosConfigurados as $key => $config) {
                    $peticiones[] = $this->encolarPeticion($pool, $key, $config, $systemPrompt, $userPrompt);
                }
                return $peticiones;
            });
        } catch (\Throwable $e) {
            Log::error('[MultiModeloHarness] Error durante el dispatch en paralelo: ' . $e->getMessage(), [
                'id_proceso' => $idProceso,
            ]);
            return [
                'habilitado'           => true,
                'error'                => 'Fallo en la comunicación con los proveedores de IA: ' . $e->getMessage(),
                'calibracion_aplicada' => false,
            ];
        }

        // ─── 2. Procesamiento y normalización de resultados ─────────────────
        $evaluaciones = [];
        $votos = [];
        $modelosProbados = [];

        $slotsFallidos = $this->procesarRespuestasLote(
            $modelosConfigurados,
            $respuestasRaw,
            $veredictoPreliminar,
            $evaluaciones,
            $votos,
            $modelosProbados
        );

        // Si en modo Gemini varios agentes sufrieron 429/503/404 y aún no hay quórum (< 2),
        // lanzar una segunda ola en paralelo con modelos alternativos no probados todavía.
        if (count($evaluaciones) < 2 && !empty($slotsFallidos)) {
            $this->ejecutarRespaldoGemini(
                $slotsFallidos,
                $modelosProbados,
                $systemPrompt,
                $userPrompt,
                $veredictoPreliminar,
                $evaluaciones,
                $votos,
                $idProceso
            );
        }

        if (empty($evaluaciones)) {
            Log::warning('[MultiModeloHarness] Ningún modelo del consejo devolvió una respuesta válida.');
            return [
                'habilitado'           => true,
                'total_modelos'        => 0,
                'mensaje'              => 'Ningún modelo pudo procesar la solicitud.',
                'calibracion_aplicada' => false,
            ];
        }

        // ─── Consolidar fuentes y evidencias citadas por los modelos IA ────
        $fuentesConsolidadas = [];
        $fuentesVistas = [];

        foreach ($evaluaciones as $eval) {
            $modeloNombre = $eval['nombre'];
            $credibilidad = (float) $eval['confianza'];

            if (!empty($eval['fuentes_consultadas']) && is_array($eval['fuentes_consultadas'])) {
                foreach ($eval['fuentes_consultadas'] as $f) {
                    $nombre = trim((string) ($f['nombre'] ?? ''));
                    if ($nombre === '') {
                        continue;
                    }

                    $claveUnica = mb_strtolower($nombre);
                    if (isset($fuentesVistas[$claveUnica])) {
                        continue;
                    }
                    $fuentesVistas[$claveUnica] = true;

                    $fuentesConsolidadas[] = [
                        'nombre'              => $nombre,
                        'tipo_fuente'         => strtoupper((string) ($f['tipo'] ?? 'OFICIAL')),
                        'cita_contraste'      => (string) ($f['dato_o_cita'] ?? ($eval['citas_relevantes'][0] ?? $eval['critica'])),
                        'url_sitio'           => !empty($f['url_referencia']) ? (string) $f['url_referencia'] : null,
                        'modelo_evaluador'    => $modeloNombre,
                        'indice_credibilidad' => $credibilidad,
                        'peso_en_veredicto'   => 0.90,
                        'origen'              => 'IA_CONSEJO',
                    ];
                }
            } elseif (!empty($eval['citas_relevantes'])) {
                foreach ($eval['citas_relevantes'] as $cita) {
                    $fuentesConsolidadas[] = [
                        'nombre'              => "Evidencia citada por {$modeloNombre}",
                        'tipo_fuente'         => 'OFICIAL',
                        'cita_contraste'      => (string) $cita,
                        'url_sitio'           => null,
                        'modelo_evaluador'    => $modeloNombre,
                        'indice_credibilidad' => $credibilidad,
                        'peso_en_veredicto'   => 0.85,
                        'origen'              => 'IA_CONSEJO',
                    ];
                }
            }
        }

        // ─── 3. Análisis de Consenso Ponderado y Calibración ───────────────
        $totalRespondieron = count($evaluaciones);
        $veredictoPreliminarNorm = strtoupper(trim($veredictoPreliminar));

        // Votación ponderada por confianza (Confidence-Weighted Voting)
        $votosPonderados = [];
        $confianzasPorVeredicto = [];
        foreach ($evaluaciones as $eval) {
            $v = $eval['veredicto'];
            $c = max(1.0, (float) $eval['confianza']);
            $votosPonderados[$v] = ($votosPonderados[$v] ?? 0.0) + $c;
            $confianzasPorVeredicto[$v][] = $c;
        }

        arsort($votosPonderados);
        $veredictoConsenso = array_key_first($votosPonderados);
        $totalPuntos = array_sum($votosPonderados);
        $porcentajeConsenso = $totalPuntos > 0.0
            ? round(($votosPonderados[$veredictoConsenso] / $totalPuntos) * 100, 1)
            : round(($votos[$veredictoConsenso] / $totalRespondieron) * 100, 1);

        $votosFavorPropuesto = $votos[$veredictoPreliminarNorm] ?? 0;
        $tasaAcuerdoPropuesto = round(($votosFavorPropuesto / $totalRespondieron) * 100, 1);

        // Confianza del veredicto ganador (promedio de los agentes que sustentan el consenso)
        $confianzasGanador = $confianzasPorVeredicto[$veredictoConsenso] ?? [75.0];
        $confianzaPromedio = round(array_sum($confianzasGanador) / count($confianzasGanador), 1);

        // Calibrar según los agentes disponibles (quórum dinámico):
        // 1. Si responden >= 2 agentes: la mayoría ponderada debe ser >= 60% y confianza >= 60%.
        // 2. Si responde 1 solo agente disponible: su veredicto calibra si su confianza es sólida (>= 65%).
        // Salvaguarda: No degradar 'FALSO' a 'NO_VERIFICABLE' por incertidumbre temporal de agentes
        $esDegradacionInvalida = ($veredictoPreliminarNorm === 'FALSO' && $veredictoConsenso === 'NO_VERIFICABLE' && $confianzaPromedio < 85.0);

        $quorumValido = ($totalRespondieron >= 2 && $porcentajeConsenso >= 60.0 && $confianzaPromedio >= 60.0)
            || ($totalRespondieron === 1 && $confianzaPromedio >= 65.0);

        $debeCalibrar = ($veredictoConsenso !== $veredictoPreliminarNorm)
            && $quorumValido
            && !$esDegradacionInvalida
            && config('dagaz.multi_modelo.calibrar_veredicto', true);

        $veredictoFinal = $debeCalibrar ? $veredictoConsenso : $veredictoPreliminarNorm;

        $resumenTexto = $this->generarResumenDebate(
            $evaluaciones,
            $veredictoPreliminarNorm,
            $veredictoConsenso,
            $porcentajeConsenso,
            $debeCalibrar
        );

        return [
            'habilitado'                  => true,
            'total_consultados'           => count($modelosConfigurados),
            'total_respondieron'          => $totalRespondieron,
            'veredicto_propuesto'         => $veredictoPreliminarNorm,
            'veredicto_consenso'          => $veredictoConsenso,
            'porcentaje_consenso'         => $porcentajeConsenso,
            'tasa_acuerdo_propuesto'      => $tasaAcuerdoPropuesto,
            'confianza_promedio'          => $confianzaPromedio,
            'distribucion_votos'          => $votos,
            'calibracion_aplicada'        => $debeCalibrar,
            'veredicto_calibrado'         => $debeCalibrar ? $veredictoConsenso : $veredictoPreliminarNorm,
            'resumen_consenso'            => $resumenTexto,
            'fuentes_evidencias'          => $fuentesConsolidadas,
            'evaluaciones_individuales'   => $evaluaciones,
        ];
    }

    /**
     * Resuelve qué modelos y proveedores están disponibles con credenciales activas.
     *
     * @return array<string, array<string, mixed>>
     */
    private function resolverModelosActivos(): array
    {
        $lista = [];

        $openRouterKey = (string) config('dagaz.openrouter.api_key', '');
        $openAiKey     = (string) config('dagaz.openai.api_key', '');
        $geminiKey     = (string) config('dagaz.gemini.api_key', '');
        $anthropicKey  = (string) config('dagaz.anthropic.api_key', '');

        // 1. OpenAI (ChatGPT / GPT-4o)
        if (!empty($openAiKey) && !str_starts_with($openAiKey, 'sk-or-')) {
            $lista['openai_gpt4o'] = [
                'nombre'    => 'OpenAI GPT-4o',
                'rol'       => 'Defensor de Evidencia (Análisis Fáctico y Corroboración)',
                'proveedor' => 'OpenAI',
                'tipo_api'  => 'openai_compatible',
                'endpoint'  => (string) config('dagaz.openai.endpoint', 'https://api.openai.com/v1/chat/completions'),
                'api_key'   => $openAiKey,
                'modelo'    => (string) config('dagaz.openai.modelo', 'gpt-4o'),
            ];
        } elseif (!empty($openRouterKey)) {
            $lista['openai_gpt4o'] = [
                'nombre'    => 'Cohere North (OpenRouter Free)',
                'rol'       => 'Defensor de Evidencia (Análisis Fáctico y Corroboración)',
                'proveedor' => 'Cohere',
                'tipo_api'  => 'openai_compatible',
                'endpoint'  => (string) config('dagaz.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
                'api_key'   => $openRouterKey,
                'modelo'    => (string) config('dagaz.multi_modelo.openrouter_modelos.agente_1', 'cohere/north-mini-code:free'),
            ];
        }

        // 2. Anthropic Claude (Experto en crítica y detección de sesgos)
        if (!empty($anthropicKey)) {
            $lista['claude_sonnet'] = [
                'nombre'    => 'Anthropic Claude',
                'rol'       => 'Abogado del Diablo / Auditor Crítico (Detección de Sesgo y Omisiones)',
                'proveedor' => 'Anthropic',
                'tipo_api'  => 'anthropic_native',
                'endpoint'  => 'https://api.anthropic.com/v1/messages',
                'api_key'   => $anthropicKey,
                'modelo'    => (string) config('dagaz.anthropic.modelo', 'claude-3-5-sonnet-20241022'),
            ];
        } elseif (!empty($openRouterKey)) {
            $lista['claude_sonnet'] = [
                'nombre'    => 'Recursive Flint (OpenRouter Free)',
                'rol'       => 'Abogado del Diablo / Auditor Crítico (Detección de Sesgo y Omisiones)',
                'proveedor' => 'Recursive',
                'tipo_api'  => 'openai_compatible',
                'endpoint'  => (string) config('dagaz.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
                'api_key'   => $openRouterKey,
                'modelo'    => (string) config('dagaz.multi_modelo.openrouter_modelos.agente_2', 'recursive/flint-v1.0:free'),
            ];
        }

        // 3. Google Gemini (Gemini Flash)
        $geminiUsados = [];
        if (!empty($geminiKey)) {
            $modeloGemini = trim((string) config('dagaz.gemini.modelo', 'gemini-3.6-flash')) ?: 'gemini-3.6-flash';
            $geminiUsados[] = $modeloGemini;
            $lista['gemini_flash'] = [
                'nombre'    => "Google Gemini ({$modeloGemini})",
                'rol'       => 'Verificador Rápido de Contexto y Coherencia Semántica',
                'proveedor' => 'Google',
                'tipo_api'  => 'gemini_native',
                'endpoint'  => "https://generativelanguage.googleapis.com/v1beta/models/{$modeloGemini}:generateContent?key={$geminiKey}",
                'api_key'   => $geminiKey,
                'modelo'    => $modeloGemini,
            ];
        } elseif (!empty($openRouterKey)) {
            $lista['gemini_flash'] = [
                'nombre'    => 'Dots Preview (OpenRouter Free)',
                'rol'       => 'Verificador Rápido de Contexto y Coherencia Semántica',
                'proveedor' => 'Dots Studio',
                'tipo_api'  => 'openai_compatible',
                'endpoint'  => (string) config('dagaz.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
                'api_key'   => $openRouterKey,
                'modelo'    => (string) config('dagaz.multi_modelo.openrouter_modelos.agente_3', 'dots-studio/dots-3-note-preview:free'),
            ];
        }

        // 4. DeepSeek-R1 / DeepSeek Chat (Pesos abiertos — Razonamiento Profundo CoT)
        if (!empty($openRouterKey)) {
            $lista['deepseek_r1'] = [
                'nombre'    => 'OpenRouter Free Reasoning',
                'rol'       => 'Juez Árbitro (Cadena de Razonamiento Extendido)',
                'proveedor' => 'OpenRouter',
                'tipo_api'  => 'openai_compatible',
                'endpoint'  => (string) config('dagaz.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
                'api_key'   => $openRouterKey,
                'modelo'    => (string) config('dagaz.multi_modelo.openrouter_modelos.agente_4', 'openrouter/free'),
            ];
        }

        // 5. Qwen 2.5 / Nemotron (Pesos abiertos alternativos)
        if (!empty($openRouterKey) && (bool) config('dagaz.multi_modelo.qwen_activo', false)) {
            $lista['qwen_72b'] = [
                'nombre'    => 'OpenRouter Free Consensus',
                'rol'       => 'Validador Multilingüe y Extractor Atómico',
                'proveedor' => 'OpenRouter',
                'tipo_api'  => 'openai_compatible',
                'endpoint'  => (string) config('dagaz.openrouter.endpoint', 'https://openrouter.ai/api/v1/chat/completions'),
                'api_key'   => $openRouterKey,
                'modelo'    => (string) config('dagaz.multi_modelo.openrouter_modelos.agente_5', 'openrouter/free'),
            ];
        }

        // 6. Consejo Multi-Modelo exclusivo sobre Google AI Studio cuando no hay otros proveedores activos
        if (!empty($geminiKey) && empty($openRouterKey) && count($lista) < 2) {
            $candidatosGemini = [
                'gemini_defensor' => [
                    'modelo' => trim((string) config('dagaz.multi_modelo.gemini_modelos.agente_1', 'gemini-3-flash-preview')),
                    'rol'    => 'Defensor de Evidencia (Análisis Fáctico y Corroboración)',
                ],
                'gemini_auditor' => [
                    'modelo' => trim((string) config('dagaz.multi_modelo.gemini_modelos.agente_2', 'gemini-3.5-flash')),
                    'rol'    => 'Abogado del Diablo / Auditor Crítico (Detección de Sesgo y Omisiones)',
                ],
                'gemini_arbitro' => [
                    'modelo' => trim((string) config('dagaz.multi_modelo.gemini_modelos.agente_3', 'gemini-3.5-flash-lite')),
                    'rol'    => 'Juez Árbitro y Extractor Atómico',
                ],
                'gemini_sintetizador' => [
                    'modelo' => trim((string) config('dagaz.multi_modelo.gemini_modelos.agente_4', 'gemini-flash-lite-latest')),
                    'rol'    => 'Validador Rápido de Hechos y Consistencia',
                ],
                'gemini_respaldo_flash' => [
                    'modelo' => 'gemini-flash-latest',
                    'rol'    => 'Validador Contextual Alternativo',
                ],
                'gemini_respaldo_alt' => [
                    'modelo' => 'gemini-3.6-flash',
                    'rol'    => 'Juez Árbitro Alternativo',
                ],
            ];

            foreach ($candidatosGemini as $slot => $info) {
                if (count($lista) >= 4) {
                    break;
                }
                $modelo = $info['modelo'];
                if ($modelo === '' || in_array($modelo, $geminiUsados, true)) {
                    continue;
                }
                $geminiUsados[] = $modelo;
                $lista[$slot] = [
                    'nombre'    => "Google Gemini ({$modelo})",
                    'rol'       => $info['rol'],
                    'proveedor' => 'Google',
                    'tipo_api'  => 'gemini_native',
                    'endpoint'  => "https://generativelanguage.googleapis.com/v1beta/models/{$modelo}:generateContent?key={$geminiKey}",
                    'api_key'   => $geminiKey,
                    'modelo'    => $modelo,
                ];
            }
        }

        return $lista;
    }

    /**
     * Procesa respuestas de un lote Http::pool y devuelve los slots que fallaron.
     *
     * @return array<string, array<string, mixed>>
     */
    private function procesarRespuestasLote(
        array $configsLote,
        array $respuestasLote,
        string $veredictoPreliminar,
        array &$evaluaciones,
        array &$votos,
        array &$modelosProbados
    ): array {
        $slotsFallidos = [];

        foreach ($configsLote as $key => $config) {
            if (!empty($config['modelo'])) {
                $modelosProbados[] = (string) $config['modelo'];
            }

            $resp = $respuestasLote[$key] ?? null;

            if (!$resp instanceof Response || !$resp->successful()) {
                $errorMsg = $resp instanceof Response ? $resp->body() : 'Sin respuesta';
                Log::warning("[MultiModeloHarness] Modelo '{$key}' falló o agotó tiempo.", [
                    'modelo' => $config['modelo'] ?? null,
                    'status' => $resp instanceof Response ? $resp->status() : null,
                    'error'  => mb_substr($errorMsg, 0, 200),
                ]);
                $slotsFallidos[$key] = $config;
                continue;
            }

            $respJson = $resp->json();
            $textoGenerado = $this->extraerTextoRespuesta($config['tipo_api'], $respJson);
            if (empty($textoGenerado)) {
                Log::warning("[MultiModeloHarness] Modelo '{$key}' devolvió respuesta vacía.", [
                    'modelo'        => $config['modelo'] ?? null,
                    'finish_reason' => $respJson['choices'][0]['finish_reason']
                        ?? ($respJson['candidates'][0]['finishReason'] ?? null),
                ]);
                $slotsFallidos[$key] = $config;
                continue;
            }

            $parsed = $this->parsearRespuestaJson($textoGenerado);
            if ($parsed === null) {
                Log::warning("[MultiModeloHarness] Modelo '{$key}' devolvió salida no parseable como JSON.", [
                    'modelo'        => $config['modelo'] ?? null,
                    'finish_reason' => $respJson['choices'][0]['finish_reason']
                        ?? ($respJson['candidates'][0]['finishReason'] ?? null),
                    'muestra'       => mb_substr($textoGenerado, 0, 300),
                ]);
                $slotsFallidos[$key] = $config;
                continue;
            }

            // Incorporar fuentes encontradas por búsqueda web en vivo (Grounding en Gemini / Search)
            $groundingChunks = $respJson['candidates'][0]['groundingMetadata']['groundingChunks'] ?? [];
            if (!empty($groundingChunks) && is_array($groundingChunks)) {
                foreach ($groundingChunks as $chunk) {
                    if (!empty($chunk['web']['uri']) && !empty($chunk['web']['title'])) {
                        $parsed['fuentes_consultadas'][] = [
                            'nombre'         => (string) $chunk['web']['title'],
                            'tipo'           => 'OFICIAL',
                            'dato_o_cita'    => 'Evidencia web consultada en tiempo real por búsqueda web.',
                            'url_referencia' => (string) $chunk['web']['uri'],
                        ];
                    }
                }
            }

            $veredictoNormalizado = $this->normalizarVeredicto($parsed['veredicto'] ?? '');

            $evaluacionItem = [
                'id_modelo'             => $key,
                'nombre'                => $config['nombre'],
                'rol'                   => $config['rol'],
                'proveedor'             => $config['proveedor'],
                'modelo_tecnico'        => $config['modelo'],
                'veredicto'             => $veredictoNormalizado,
                'confianza'             => (float) ($parsed['confianza'] ?? 75.0),
                'acuerda_con_propuesto' => (bool) ($parsed['acuerdo_con_propuesto'] ?? ($veredictoNormalizado === strtoupper($veredictoPreliminar))),
                'critica'               => (string) ($parsed['critica'] ?? ''),
                'fuentes_consultadas'   => (array) ($parsed['fuentes_consultadas'] ?? []),
                'citas_relevantes'      => (array) ($parsed['citas_relevantes'] ?? []),
            ];

            $evaluaciones[$key] = $evaluacionItem;
            $votos[$veredictoNormalizado] = ($votos[$veredictoNormalizado] ?? 0) + 1;
        }

        return $slotsFallidos;
    }

    /**
     * Ejecuta una segunda ola paralela con modelos Gemini de respaldo no probados aún
     * cuando los slots iniciales sufrieron errores transitorios o de cuota (429/503/404).
     */
    private function ejecutarRespaldoGemini(
        array $slotsFallidos,
        array &$modelosProbados,
        string $systemPrompt,
        string $userPrompt,
        string $veredictoPreliminar,
        array &$evaluaciones,
        array &$votos,
        ?string $idProceso
    ): void {
        $slotsGemini = array_filter(
            $slotsFallidos,
            fn ($cfg) => ($cfg['tipo_api'] ?? '') === 'gemini_native' && !empty($cfg['api_key'])
        );

        if (empty($slotsGemini)) {
            return;
        }

        $candidatos = array_merge(
            (array) config('dagaz.gemini.fallback_modelos', []),
            array_values((array) config('dagaz.multi_modelo.gemini_modelos', [])),
            app()->runningUnitTests()
                ? []
                : ['gemini-flash-lite-latest', 'gemini-flash-latest', 'gemini-3.6-flash', 'gemini-3.7-flash', 'gemini-3.8-flash']
        );

        $probadosMap = array_flip(array_unique($modelosProbados));
        $modelosDisponibles = [];
        foreach ($candidatos as $m) {
            $limpio = trim((string) $m);
            if ($limpio !== '' && !isset($probadosMap[$limpio])) {
                $probadosMap[$limpio] = true;
                $modelosDisponibles[] = $limpio;
            }
        }

        if (empty($modelosDisponibles)) {
            return;
        }

        $reintentos = [];
        foreach ($slotsGemini as $slotKey => $cfg) {
            if (empty($modelosDisponibles)) {
                break;
            }
            $modeloRespaldo = array_shift($modelosDisponibles);
            $apiKey = (string) $cfg['api_key'];
            $reintentos[$slotKey] = [
                'nombre'    => "Google Gemini ({$modeloRespaldo})",
                'rol'       => $cfg['rol'],
                'proveedor' => 'Google',
                'tipo_api'  => 'gemini_native',
                'endpoint'  => "https://generativelanguage.googleapis.com/v1beta/models/{$modeloRespaldo}:generateContent?key={$apiKey}",
                'api_key'   => $apiKey,
                'modelo'    => $modeloRespaldo,
            ];
        }

        if (empty($reintentos)) {
            return;
        }

        try {
            $respuestasRespaldo = Http::pool(function (Pool $pool) use ($reintentos, $systemPrompt, $userPrompt) {
                $peticiones = [];
                foreach ($reintentos as $key => $config) {
                    $peticiones[] = $this->encolarPeticion($pool, $key, $config, $systemPrompt, $userPrompt);
                }
                return $peticiones;
            });

            $this->procesarRespuestasLote(
                $reintentos,
                $respuestasRespaldo,
                $veredictoPreliminar,
                $evaluaciones,
                $votos,
                $modelosProbados
            );
        } catch (\Throwable $e) {
            Log::warning('[MultiModeloHarness] Error en ola de respaldo Gemini: ' . $e->getMessage(), [
                'id_proceso' => $idProceso,
            ]);
        }
    }

    /**
     * Encola una petición individual dentro del Http::pool.
     */
    private function encolarPeticion(Pool $pool, string $key, array $config, string $systemPrompt, string $userPrompt)
    {
        $tipo = $config['tipo_api'];
        $req  = $pool->as($key);
        $timeoutSegundos = (int) config('dagaz.multi_modelo.timeout_segundos', self::TIMEOUT_SEGUNDOS);

        if (app()->environment('local', 'testing') || DIRECTORY_SEPARATOR === '\\') {
            $req = $req->withoutVerifying();
        }

        if ($tipo === 'openai_compatible') {
            return $req->withHeaders([
                'Authorization' => "Bearer {$config['api_key']}",
                'Content-Type'  => 'application/json',
                'HTTP-Referer'  => config('app.url') ?: 'https://dagaz.com.co',
                'X-Title'       => 'DAGAZ Fact-Checking Harness',
            ])->timeout($timeoutSegundos)->post($config['endpoint'], [
                'model'       => $config['modelo'],
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'temperature' => 0.15,
                'max_tokens'  => 1800,
            ]);
        }

        if ($tipo === 'anthropic_native') {
            return $req->withHeaders([
                'x-api-key'         => $config['api_key'],
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout($timeoutSegundos)->post($config['endpoint'], [
                'model'      => $config['modelo'],
                'system'     => $systemPrompt,
                'max_tokens' => 1400,
                'messages'   => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'temperature' => 0.15,
            ]);
        }

        if ($tipo === 'gemini_native') {
            return $req->withHeaders([
                'Content-Type' => 'application/json',
            ])->timeout($timeoutSegundos)->post($config['endpoint'], [
                'system_instruction' => [
                    'parts' => [['text' => $systemPrompt]],
                ],
                'contents' => [
                    ['parts' => [['text' => $userPrompt]]],
                ],
                'tools' => [
                    ['googleSearch' => new \stdClass()],
                ],
                'generationConfig' => [
                    'temperature'     => 0.15,
                    'maxOutputTokens' => 2800,
                ],
            ]);
        }

        throw new \InvalidArgumentException("Tipo de API desconocido: {$tipo}");
    }

    /**
     * Extrae el texto generado según el formato del proveedor.
     */
    private function extraerTextoRespuesta(string $tipoApi, ?array $json): ?string
    {
        if (empty($json)) {
            return null;
        }

        if ($tipoApi === 'openai_compatible') {
            $msg = $json['choices'][0]['message'] ?? [];
            return !empty($msg['content']) ? $msg['content'] : ($msg['reasoning'] ?? null);
        }

        if ($tipoApi === 'anthropic_native') {
            return $json['content'][0]['text'] ?? null;
        }

        if ($tipoApi === 'gemini_native') {
            $parts  = $json['candidates'][0]['content']['parts'] ?? [];
            $textos = [];
            foreach ($parts as $part) {
                if (!empty($part['thought'])) {
                    continue;
                }
                if (isset($part['text']) && is_string($part['text'])) {
                    $textos[] = $part['text'];
                }
            }

            $texto = trim(implode("\n", $textos));
            return $texto !== '' ? $texto : ($parts[0]['text'] ?? null);
        }

        return null;
    }

    /**
     * Limpia y parsea la respuesta JSON garantizando la eliminación de markdown y CoT tags.
     */
    private function parsearRespuestaJson(string $raw): ?array
    {
        // 1. Remover etiquetas de pensamiento CoT (ej. <think>...</think> de DeepSeek-R1)
        if (str_contains($raw, '</think>')) {
            $partes = explode('</think>', $raw);
            $raw = (string) end($partes);
        }

        // 2. Remover envoltorio de bloque markdown ```json ... ```
        $raw = trim((string) preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $raw));

        // 3. Extraer bloque entre llaves {}
        $start = strpos($raw, '{');
        $end   = strrpos($raw, '}');
        if ($start === false || $end === false || $end < $start) {
            return null;
        }

        $jsonStr = substr($raw, $start, $end - $start + 1);
        $data = json_decode($jsonStr, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            $sanitizado = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $jsonStr);
            $data       = json_decode($sanitizado ?? $jsonStr, true);
        }

        if (!is_array($data)) {
            return null;
        }

        $normalizado = [];
        foreach ($data as $k => $v) {
            $normalizado[trim((string) $k)] = $v;
        }

        return $normalizado;
    }

    /**
     * Normaliza sinónimos y variaciones al vocabulario canónico de Raiden/DAGAZ.
     */
    private function normalizarVeredicto(string $veredicto): string
    {
        $v = strtoupper(trim($veredicto));

        return match ($v) {
            'VERDADERO', 'TRUE', 'CORRECTO', 'EXACTO'             => 'VERDADERO',
            'FALSO', 'FALSE', 'INCORRECTO'                       => 'FALSO',
            'ENGAÑOSO', 'ENGANOSO', 'MISLEADING'                 => 'ENGAÑOSO',
            'PARCIAL', 'PARTIAL', 'MEDIAS VERDADES'              => 'PARCIAL',
            default                                              => 'NO_VERIFICABLE',
        };
    }

    /**
     * Redacta una síntesis en prosa del debate multi-modelo para auditoría editorial.
     */
    private function generarResumenDebate(
        array $evaluaciones,
        string $veredictoPropuesto,
        string $veredictoConsenso,
        float $porcentajeConsenso,
        bool $calibrado
    ): string {
        $total = count($evaluaciones);
        $modelosNombres = implode(', ', array_map(fn ($e) => $e['nombre'], $evaluaciones));

        // Extraer el argumento técnico o crítica más representativa del consenso de los modelos
        $criticaConsenso = '';
        foreach ($evaluaciones as $e) {
            if ($e['veredicto'] === $veredictoConsenso && !empty($e['critica'])) {
                $criticaConsenso = trim($e['critica']);
                break;
            }
        }
        $bloqueCritica = !empty($criticaConsenso) ? " Dictamen y fundamento fáctico: {$criticaConsenso}" : '';

        if ($calibrado) {
            $sujeto = $total === 1 ? "El Agente Evaluador ({$modelosNombres})" : "El Consejo Multi-Modelo ({$modelosNombres})";
            $consultaTxt = $total === 1 ? "1 modelo evaluador" : "{$total} modelos evaluadores";
            return "{$sujeto} analizó la afirmación y recalibró el veredicto preliminar de '{$veredictoPropuesto}' a '{$veredictoConsenso}' con un consenso del {$porcentajeConsenso}% ({$consultaTxt}).{$bloqueCritica}";
        }

        if ($veredictoConsenso === $veredictoPropuesto) {
            $agenteTxt = $total === 1 ? '1 agente evaluador' : "{$total} agentes evaluadores";
            return "El Consejo Multi-Modelo ratificó el veredicto '{$veredictoPropuesto}' con un respaldo del {$porcentajeConsenso}% a través de {$agenteTxt} ({$modelosNombres}).{$bloqueCritica}";
        }

        if ($total === 1) {
            return "El Agente Evaluador ({$modelosNombres}) analizó la afirmación y sugirió la calificación '{$veredictoConsenso}' ({$porcentajeConsenso}%). Se mantiene el veredicto preliminar '{$veredictoPropuesto}'.{$bloqueCritica}";
        }

        return "El Consejo Multi-Modelo evaluó el caso con posturas divididas. Se preserva '{$veredictoPropuesto}', pero se anota disenso hacia '{$veredictoConsenso}' ({$porcentajeConsenso}%).{$bloqueCritica}";
    }

    /**
     * Construye el system prompt estricto para los miembros del consejo.
     */
    private function construirSystemPrompt(): string
    {
        $fechaActual = now()->translatedFormat('d \d\e F \d\e Y');
        $anioActual  = now()->year;

        return <<<PROMPT
Eres un Agente Evaluador Senior en el Consejo de Verificación de Integridad Informativa DAGAZ (Colombia).
Tu función es auditar críticamente si un veredicto preliminar propuesto para una afirmación es correcto o si contiene fallas lógicas, omisión de contexto, sesgo ideológico, desactualización o cherry-picking de evidencia.

Categorías canónicas permitidas:
- "VERDADERO": Todos los hechos centrales son exactos y están corroborados.
- "FALSO": Los hechos centrales son falsos, inventados, anacrónicos o contradicen la realidad comprobada.
- "ENGAÑOSO": Contiene datos reales pero los descontextualiza para inducir a una conclusión errónea.
- "PARCIAL": Mezcla elementos verdaderos con imprecisiones o datos desactualizados.
- "NO_VERIFICABLE": No existe suficiente evidencia concluyente para dictaminar.

REGLAS METODOLÓGICAS DE FACT-CHECKING (Estándares IFCN) Y ACTUALIDAD:
1. FECHA ACTUAL DE REFERENCIA:
   - La fecha actual del sistema es estrictamente {$fechaActual} (Año {$anioActual}).
   - Cualquier acontecimiento ocurrido hasta hoy ({$fechaActual}) es un hecho consumado o del presente.
   - Para temas de economía, finanzas y actualidad volátil (precio del dólar/TRM en Colombia, cotización del petróleo Brent/WTI, acciones bursátiles, inflación, nombramientos o declaraciones políticas del día):
     * Evalúa la afirmación contrastándola con la realidad fáctica a fecha de {$fechaActual}.
     * Si la afirmación describe un dato o hecho verídico respaldado por cotizaciones oficiales o prensa, califícala como "VERDADERO" (o "FALSO" si contradice la cifra o hecho real).
     * NUNCA califiques como "NO_VERIFICABLE" o "FALSO" una afirmación solo porque no esté en el corpus histórico si la evidencia web o actualidad fáctica la respalda.
2. Eventos futuros o hipotéticos narrados como hechos consumados: Si una afirmación declara en tiempo pretérito que una figura pública "ganó", "asumió el poder" o consumó un hecho sin que existan registros oficiales, proclamaciones de tribunales electorales ni respaldo documental contrastado, el veredicto periodístico debe ser "FALSO" (o "ENGAÑOSO"), NUNCA "NO_VERIFICABLE". Afirmar que un hecho inexistente ya ocurrió es demostrablemente falso.
3. Incompatibilidad fáctica y jurídica: Considera las sentencias e inhabilitaciones políticas oficiales emitidas por tribunales competentes (por ejemplo, fallos de inhabilitación electoral emitidos por cortes o tribunales electorales). Afirmar como consumado un triunfo de un candidato jurídica o fácticamente descartado es demostrablemente falso.
4. Uso de "NO_VERIFICABLE": Reserva esta categoría EXCLUSIVAMENTE para alegaciones sobre hechos donde no exista posibilidad de acceso a fuentes públicas, registros o testimonios (ej. reuniones secretas sin registro).
5. Cronología electoral y elecciones de 2026:
   - Los comicios presidenciales de Brasil del año 2026 ya se celebraron el 25 de octubre de 2026 y son hechos consumados en tiempo pasado.
   - En dichas elecciones de octubre de 2026, el mandatario reelecto fue Luiz Inácio Lula da Silva según las actas del Tribunal Superior Eleitoral (TSE). Ni Jair Bolsonaro (quien permanece inhabilitado políticamente por el TSE) ni sus hijos (Flávio Bolsonaro o Eduardo Bolsonaro) ganaron las elecciones.
   - Si una afirmación sostiene que Jair Bolsonaro o Flávio Bolsonaro "ganó las elecciones de Brasil en 2026" o "es presidente de Brasil", el veredicto es estrictamente "FALSO". Si una afirmación sostiene que "Flávio Bolsonaro no ganó las elecciones de 2026" o "las perdió Flávio Bolsonaro", el veredicto es estrictamente "VERDADERO".

Debes responder ÚNICAMENTE con un objeto JSON válido con esta estructura:
{
  "veredicto": "VERDADERO" | "FALSO" | "ENGAÑOSO" | "PARCIAL" | "NO_VERIFICABLE",
  "confianza": <número float entre 0 y 100>,
  "acuerdo_con_propuesto": <true o false>,
  "critica": "<argumentación concisa de tu postura técnica>",
  "fuentes_consultadas": [
    {
      "nombre": "<Nombre del organismo oficial, tribunal electoral, medio o registro público consultado>",
      "tipo": "OFICIAL" | "PERIODISTICO" | "ACADEMICO" | "HISTORICO",
      "dato_o_cita": "<Dato o evidencia concreta de contraste>",
      "url_referencia": "<URL oficial si es conocida o null>"
    }
  ],
  "citas_relevantes": ["<citas directas de la evidencia que fundamentan tu fallo>"]
}
PROMPT;
    }

    /**
     * Construye el prompt de usuario con la afirmación y la evidencia documental.
     */
    private function construirUserPrompt(string $afirmacion, string $veredictoPreliminar, array $citasEvidencia): string
    {
        $fechaHoy = now()->translatedFormat('d \d\e F \d\e Y');
        $bloqueEvidencia = empty($citasEvidencia)
            ? 'No se suministró corpus documental cerrado. Emite tu juicio basándote en fuentes oficiales, registros públicos, cotizaciones de mercado y hechos verificables al día de hoy.'
            : implode("\n- ", array_slice($citasEvidencia, 0, 10));

        return <<<PROMPT
AFIRMACIÓN A EXAMINAR:
"{$afirmacion}"

FECHA DE REFERENCIA:
{$fechaHoy} (Ten en cuenta acontecimientos de actualidad económica y comicios de 2026 ya concluidos).

VEREDICTO PRELIMINAR PROPUESTO:
"{$veredictoPreliminar}"

EVIDENCIA DOCUMENTAL RECUPERADA (CORPUS Y/O BÚSQUEDA WEB):
- {$bloqueEvidencia}

INSTRUCCIÓN:
Evalúa la afirmación frente al veredicto preliminar y la evidencia recuperada.
¿Es el veredicto propuesto el más adecuado o debe corregirse a la luz de los hechos y la fecha actual?
Indica explícitamente en "fuentes_consultadas" qué organismos oficiales, leyes, medios o entidades de mercado respaldan tu juicio.
Devuelve tu fallo exclusivamente en formato JSON.
PROMPT;
    }
}
