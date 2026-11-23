<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Adapters\Ia\GeminiDriver;
use App\Adapters\Ia\IaDriverFactory;
use App\Services\MultiModeloHarnessService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiDriverTest extends TestCase
{
    public function test_gemini_driver_ignora_partes_thought_y_parsea_justificacion_tildada(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text'    => 'Razonamiento interno del modelo...',
                                    'thought' => true,
                                ],
                                [
                                    'text' => "```json\n{\"score\": 0.05, \"justificación\": \"No existe respaldo oficial para esta afirmación.\"}\n```",
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $driver = new GeminiDriver(
            apiKey: 'AIza-test-key',
            modelo: 'gemini-3.6-flash'
        );

        $resultado = $driver->verificarCoherencia("Afirmación falsa\n\nEVIDENCIA DOCUMENTAL ACTUALIZADA EN LA BASE DE DATOS:\n- Registro oficial");

        $this->assertSame(0.05, $resultado['score']);
        $this->assertSame('No existe respaldo oficial para esta afirmación.', $resultado['justificacion']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-3.6-flash:generateContent?key=AIza-test-key');
        });
    }

    public function test_multi_modelo_harness_activa_consejo_gemini_con_quorum_y_calibracion_sin_openrouter(): void
    {
        $originalConfig = config('dagaz');

        try {
            config([
                'dagaz.ia_proveedor'                            => 'gemini',
                'dagaz.openai.api_key'                          => '',
                'dagaz.openrouter.api_key'                      => '',
                'dagaz.anthropic.api_key'                       => '',
                'dagaz.gemini.api_key'                          => 'AIza-test-only-gemini',
                'dagaz.gemini.modelo'                           => 'gemini-3.6-flash',
                'dagaz.multi_modelo.habilitado'                 => true,
                'dagaz.multi_modelo.calibrar_veredicto'         => true,
                'dagaz.multi_modelo.gemini_modelos.agente_1'    => 'gemini-3-flash-preview',
                'dagaz.multi_modelo.gemini_modelos.agente_2'    => 'gemini-3.5-flash',
                'dagaz.multi_modelo.gemini_modelos.agente_3'    => 'gemini-3.5-flash-lite',
            ]);

            $driver = IaDriverFactory::crear();
            $this->assertInstanceOf(GeminiDriver::class, $driver);

            Http::fake([
                'https://generativelanguage.googleapis.com/*' => Http::response([
                    'candidates' => [
                        [
                            'finishReason' => 'STOP',
                            'content'      => [
                                'parts' => [
                                    [
                                        'text' => json_encode([
                                            'veredicto'             => 'FALSO',
                                            'confianza'             => 96.0,
                                            'acuerdo_con_propuesto' => false,
                                            'critica'               => 'Afirmación sin sustento fáctico ni documental.',
                                            'fuentes_consultadas'   => [
                                                [
                                                    'nombre'         => 'Registraduría Nacional',
                                                    'tipo'           => 'OFICIAL',
                                                    'dato_o_cita'    => 'No existen registros que respalden la afirmación.',
                                                    'url_referencia' => null,
                                                ],
                                            ],
                                            'citas_relevantes' => [],
                                        ]),
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 200),
            ]);

            /** @var MultiModeloHarnessService $harness */
            $harness = app(MultiModeloHarnessService::class);
            $resultado = $harness->evaluarConConsejo('Afirmación sin sustento', 'ENGAÑOSO');

            $this->assertTrue($resultado['habilitado']);
            $this->assertSame(4, $resultado['total_consultados']);
            $this->assertSame(4, $resultado['total_respondieron']);
            $this->assertTrue($resultado['calibracion_aplicada']);
            $this->assertSame('FALSO', $resultado['veredicto_consenso']);
            $this->assertSame('FALSO', $resultado['veredicto_calibrado']);
            $this->assertNotEmpty($resultado['fuentes_evidencias']);
        } finally {
            config(['dagaz' => $originalConfig]);
        }
    }

    public function test_gemini_driver_usa_modelo_fallback_si_principal_devuelve_429_o_503(): void
    {
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash*' => Http::response([
                'error' => ['code' => 429, 'message' => 'Quota exceeded'],
            ], 429),
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"score": 0.1, "justificacion": "Respaldado por modelo de respaldo."}'],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $driver = new GeminiDriver(
            apiKey: 'AIza-test-key',
            modelo: 'gemini-3.6-flash',
            fallbackModelos: ['gemini-3-flash-preview']
        );

        $resultado = $driver->verificarCoherencia('Afirmación de prueba');

        $this->assertSame(0.1, $resultado['score']);
        $this->assertSame('Respaldado por modelo de respaldo.', $resultado['justificacion']);
    }

    public function test_gemini_driver_sanitiza_caracteres_de_control_y_claves_con_saltos(): void
    {
        // Usar cadena con salto de línea literal dentro de la clave JSON como devuelve gemini-3.5-flash-lite
        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => "{\"\nscore\": 0.0, \"justificacion\": \"Sin sustento factual.\"}"],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $driver = new GeminiDriver(apiKey: 'AIza-test-key', modelo: 'gemini-3.5-flash-lite');
        $resultado = $driver->verificarCoherencia('Afirmación vulgar sin respaldo');

        $this->assertSame(0.0, $resultado['score']);
        $this->assertSame('Sin sustento factual.', $resultado['justificacion']);
    }

    public function test_multi_modelo_harness_mantiene_cuatro_modelos_si_principal_coincide_y_rescata_ola_2(): void
    {
        $originalConfig = config('dagaz');

        try {
            config([
                'dagaz.ia_proveedor'                         => 'gemini',
                'dagaz.openai.api_key'                       => '',
                'dagaz.openrouter.api_key'                   => '',
                'dagaz.anthropic.api_key'                    => '',
                'dagaz.gemini.api_key'                       => 'AIza-test-only-gemini',
                'dagaz.gemini.modelo'                        => 'gemini-3.5-flash',
                'dagaz.gemini.fallback_modelos'              => ['gemini-respaldo-ola2'],
                'dagaz.multi_modelo.habilitado'              => true,
                'dagaz.multi_modelo.calibrar_veredicto'      => true,
                'dagaz.multi_modelo.gemini_modelos.agente_1' => 'gemini-3-flash-preview',
                'dagaz.multi_modelo.gemini_modelos.agente_2' => 'gemini-3.5-flash',
                'dagaz.multi_modelo.gemini_modelos.agente_3' => 'gemini-3.5-flash-lite',
                'dagaz.multi_modelo.gemini_modelos.agente_4' => 'gemini-flash-lite-latest',
            ]);

            $payloadFalso = [
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content'      => [
                        'parts' => [[
                            'text' => json_encode([
                                'veredicto'             => 'FALSO',
                                'confianza'             => 99.0,
                                'acuerdo_con_propuesto' => false,
                                'critica'               => 'Afirmación sin sustento fáctico.',
                                'fuentes_consultadas'   => [],
                                'citas_relevantes'      => [],
                            ]),
                        ]],
                    ],
                ]],
            ];

            Http::fake([
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash:*'        => Http::response(['error' => ['code' => 429]], 429),
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:*'  => Http::response(['error' => ['code' => 503]], 503),
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-lite-latest:*' => Http::response(['error' => ['code' => 503]], 503),
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:*'   => Http::response($payloadFalso, 200),
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-respaldo-ola2:*'    => Http::response($payloadFalso, 200),
            ]);

            /** @var MultiModeloHarnessService $harness */
            $harness = app(MultiModeloHarnessService::class);
            $resultado = $harness->evaluarConConsejo('Afirmación sin sustento', 'ENGAÑOSO');

            $this->assertTrue($resultado['habilitado']);
            $this->assertSame(4, $resultado['total_consultados']);
            $this->assertSame(2, $resultado['total_respondieron']);
            $this->assertTrue($resultado['calibracion_aplicada']);
            $this->assertSame('FALSO', $resultado['veredicto_consenso']);
            $this->assertSame('FALSO', $resultado['veredicto_calibrado']);
        } finally {
            config(['dagaz' => $originalConfig]);
        }
    }
}
