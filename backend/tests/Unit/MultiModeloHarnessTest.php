<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MultiModeloHarnessService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiModeloHarnessTest extends TestCase
{
    private MultiModeloHarnessService $harness;

    protected function setUp(): void
    {
        parent::setUp();
        $this->harness = new MultiModeloHarnessService();
    }

    public function test_quorum_dinamico_calibra_con_un_solo_agente_disponible_si_confianza_es_alta(): void
    {
        Config::set('dagaz.multi_modelo.habilitado', true);
        Config::set('dagaz.multi_modelo.calibrar_veredicto', true);
        Config::set('dagaz.gemini.api_key', 'test_key');
        Config::set('dagaz.openai.api_key', '');
        Config::set('dagaz.openrouter.api_key', '');
        Config::set('dagaz.anthropic.api_key', '');

        Config::set('dagaz.gemini.modelo', 'gemini-3.6-flash');

        // Mockear que solo el agente principal de Gemini responde exitosamente (los demás fallan con 503)
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'veredicto'             => 'VERDADERO',
                                        'confianza'             => 92.0,
                                        'acuerdo_con_propuesto' => false,
                                        'critica'               => 'La afirmación es exacta conforme al precio del dólar de hoy.',
                                        'fuentes_consultadas'   => [
                                            [
                                                'nombre'         => 'Banco de la República',
                                                'tipo'           => 'OFICIAL',
                                                'dato_o_cita'    => 'TRM vigente hoy: 4.250 COP',
                                                'url_referencia' => 'https://banrep.gov.co',
                                            ],
                                        ],
                                        'citas_relevantes'      => ['TRM oficial registrada en 4.250 pesos'],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $resultado = $this->harness->evaluarConConsejo(
            afirmacion: 'El precio del dólar hoy cerró en 4.250 pesos.',
            veredictoPreliminar: 'FALSO',
            citasEvidencia: []
        );

        $this->assertTrue($resultado['habilitado']);
        $this->assertSame(1, $resultado['total_respondieron']);
        $this->assertSame('VERDADERO', $resultado['veredicto_consenso']);
        // Quórum dinámico: debe calibrar aunque solo 1 agente respondió
        $this->assertTrue($resultado['calibracion_aplicada'], 'Debe calibrar con 1 agente si confianza >= 65%');
        $this->assertSame('VERDADERO', $resultado['veredicto_calibrado']);
        $this->assertStringContainsString('El Agente Evaluador', $resultado['resumen_consenso']);
        $this->assertStringContainsString('calibró el veredicto preliminar', $resultado['resumen_consenso']);
        $this->assertNotEmpty($resultado['fuentes_evidencias']);
        $this->assertSame('Banco de la República', $resultado['fuentes_evidencias'][0]['nombre']);
    }

    public function test_no_calibra_si_unico_agente_tiene_baja_confianza(): void
    {
        Config::set('dagaz.multi_modelo.habilitado', true);
        Config::set('dagaz.multi_modelo.calibrar_veredicto', true);
        Config::set('dagaz.gemini.api_key', 'test_key');
        Config::set('dagaz.openai.api_key', '');
        Config::set('dagaz.openrouter.api_key', '');
        Config::set('dagaz.anthropic.api_key', '');

        Config::set('dagaz.gemini.modelo', 'gemini-3.6-flash');

        // Mockear que solo el agente principal de Gemini responde con confianza baja (50%)
        Http::fake([
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'veredicto'             => 'VERDADERO',
                                        'confianza'             => 50.0,
                                        'acuerdo_con_propuesto' => false,
                                        'critica'               => 'Incertidumbre en los datos.',
                                        'fuentes_consultadas'   => [],
                                        'citas_relevantes'      => [],
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Service Unavailable'], 503),
        ]);

        $resultado = $this->harness->evaluarConConsejo(
            afirmacion: 'Afirmación dudosa',
            veredictoPreliminar: 'FALSO',
            citasEvidencia: []
        );

        $this->assertTrue($resultado['habilitado']);
        $this->assertSame(1, $resultado['total_respondieron']);
        $this->assertFalse($resultado['calibracion_aplicada']);
        $this->assertSame('FALSO', $resultado['veredicto_calibrado']);
    }

    public function test_incorporacion_de_grounding_web_chunks(): void
    {
        Config::set('dagaz.multi_modelo.habilitado', true);
        Config::set('dagaz.gemini.api_key', 'test_key');
        Config::set('dagaz.openai.api_key', '');
        Config::set('dagaz.openrouter.api_key', '');
        Config::set('dagaz.anthropic.api_key', '');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'veredicto'             => 'VERDADERO',
                                        'confianza'             => 95.0,
                                        'acuerdo_con_propuesto' => true,
                                        'critica'               => 'Confirmado con fuentes web en tiempo real.',
                                        'fuentes_consultadas'   => [],
                                        'citas_relevantes'      => [],
                                    ]),
                                ],
                            ],
                        ],
                        'groundingMetadata' => [
                            'groundingChunks' => [
                                [
                                    'web' => [
                                        'title' => 'Cotización Dólar Colombia',
                                        'uri'   => 'https://dolar-colombia.com',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $resultado = $this->harness->evaluarConConsejo(
            afirmacion: 'Cotización dólar hoy',
            veredictoPreliminar: 'VERDADERO',
            citasEvidencia: []
        );

        $this->assertNotEmpty($resultado['fuentes_evidencias']);
        $this->assertSame('Cotización Dólar Colombia', $resultado['fuentes_evidencias'][0]['nombre']);
        $this->assertSame('https://dolar-colombia.com', $resultado['fuentes_evidencias'][0]['url_sitio']);
    }
}
