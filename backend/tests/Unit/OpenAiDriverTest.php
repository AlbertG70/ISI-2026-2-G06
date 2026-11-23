<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Adapters\Ia\OpenAiDriver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiDriverTest extends TestCase
{
    public function test_openrouter_no_envia_reasoning_effort_none_y_parsea_think_con_justificacion_tildada(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => "<think>Razonamiento previo</think>\n```json\n{\"score\": 0.0, \"justificación\": \"Flavio Bolsonaro perdió las elecciones del 25 de octubre de 2026.\"}\n```",
                        ],
                    ],
                ],
            ], 200),
        ]);

        $driver = new OpenAiDriver(
            apiKey: 'sk-or-v1-test-key',
            modelo: 'openrouter/free'
        );

        $resultado = $driver->verificarCoherencia('Flavio Bolsonaro es presidente de Brasil');

        $this->assertSame(0.0, $resultado['score']);
        $this->assertSame('Flavio Bolsonaro perdió las elecciones del 25 de octubre de 2026.', $resultado['justificacion']);

        Http::assertSent(function ($request) {
            $data = $request->data();
            return str_contains($request->url(), 'openrouter.ai')
                && !array_key_exists('reasoning', $data);
        });
    }

    public function test_openrouter_usa_campo_reasoning_si_content_esta_vacio(): void
    {
        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content'   => '',
                            'reasoning' => "Análisis completado.\n{\"score\": 0.95, \"justificacion\": \"El hecho está confirmado por el TSE.\"}",
                        ],
                    ],
                ],
            ], 200),
        ]);

        $driver = new OpenAiDriver(
            apiKey: 'sk-or-v1-test-key',
            modelo: 'openrouter/free',
            endpoint: 'https://openrouter.ai/api/v1/chat/completions',
            nombreProveedor: 'OpenRouter'
        );

        $resultado = $driver->verificarCoherencia('Luiz Inácio Lula da Silva ganó las elecciones de Brasil');

        $this->assertSame(0.95, $resultado['score']);
        $this->assertSame('El hecho está confirmado por el TSE.', $resultado['justificacion']);
    }
}
