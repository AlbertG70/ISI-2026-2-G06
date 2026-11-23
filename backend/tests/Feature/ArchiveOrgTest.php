<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\ConectorArchiveInterface;
use App\Models\LogComunicadorApi;
use App\Models\Usuario;
use App\Services\ArchiveOrgService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ArchiveOrgTest — Suite de Pruebas para Conector Archive.org / Wayback Machine
 * TSK-3.2 & REQ-10: Recuperación de versiones históricas para detectar descontextualización.
 */
class ArchiveOrgTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = Usuario::create([
            'email'         => 'adulto.archive@dagaz.com',
            'password_hash' => bcrypt('Password123!'),
            'rol'           => 'adulto',
        ]);

        $this->token = $this->usuario->createToken('test-token')->plainTextToken;
    }

    public function test_consultar_disponibilidad_detecta_snapshot_existente(): void
    {
        $targetUrl = 'https://ejemplo-noticias.com/noticia-electoral';

        Http::fake([
            'https://archive.org/wayback/available*' => Http::response([
                'url' => $targetUrl,
                'archived_snapshots' => [
                    'closest' => [
                        'status'    => '200',
                        'available' => true,
                        'url'       => 'http://web.archive.org/web/20180515120000/' . $targetUrl,
                        'timestamp' => '20180515120000',
                    ],
                ],
            ], 200),
        ]);

        /** @var ArchiveOrgService $service */
        $service = app(ConectorArchiveInterface::class);
        $res = $service->consultarDisponibilidad($targetUrl);

        $this->assertTrue($res['disponible']);
        $this->assertStringContainsString('20180515120000', $res['url_captura']);
        $this->assertSame('20180515120000', $res['timestamp']);
        $this->assertSame(200, $res['codigo_http']);

        // Verificar log de auditoría
        $this->assertDatabaseHas('logs_comunicador_api', [
            'servicio'         => 'Archive.org',
            'tipo_operacion'   => 'historico_wayback',
            'codigo_respuesta' => 200,
        ]);
    }

    public function test_consultar_disponibilidad_maneja_url_no_archivada(): void
    {
        $targetUrl = 'https://nuevo-sitio-sin-historial.org';

        Http::fake([
            'https://archive.org/wayback/available*' => Http::response([
                'url' => $targetUrl,
                'archived_snapshots' => [],
            ], 200),
        ]);

        /** @var ArchiveOrgService $service */
        $service = app(ConectorArchiveInterface::class);
        $res = $service->consultarDisponibilidad($targetUrl);

        $this->assertFalse($res['disponible']);
        $this->assertNull($res['url_captura']);
        $this->assertSame(200, $res['codigo_http']);
    }

    public function test_consultar_disponibilidad_maneja_error_de_red_sin_excepcion_fatal(): void
    {
        $targetUrl = 'https://caida-archive.org';

        Http::fake([
            'https://archive.org/wayback/available*' => Http::response('Gateway Timeout', 504),
        ]);

        /** @var ArchiveOrgService $service */
        $service = app(ConectorArchiveInterface::class);
        $res = $service->consultarDisponibilidad($targetUrl);

        $this->assertFalse($res['disponible']);
        $this->assertSame(504, $res['codigo_http']);
    }

    public function test_obtener_capturas_historicas_parsea_cdx(): void
    {
        $targetUrl = 'https://colombia-elecciones.co/articulo';

        Http::fake([
            'https://web.archive.org/cdx/search/cdx*' => Http::response([
                ['timestamp', 'original', 'mimetype', 'statuscode', 'digest'],
                ['20190310100000', $targetUrl, 'text/html', '200', 'DIGEST1'],
                ['20220619143000', $targetUrl, 'text/html', '200', 'DIGEST2'],
            ], 200),
        ]);

        /** @var ArchiveOrgService $service */
        $service = app(ConectorArchiveInterface::class);
        $capturas = $service->obtenerCapturasHistoricas($targetUrl, 5);

        $this->assertCount(2, $capturas);
        $this->assertSame('20190310100000', $capturas[0]['timestamp']);
        $this->assertSame('2019-03-10 10:00:00', $capturas[0]['fecha_hora']);
        $this->assertStringContainsString('https://web.archive.org/web/20190310100000/', $capturas[0]['url_wayback']);
    }

    public function test_detectar_descontextualizacion_alerta_sobre_noticia_antigua(): void
    {
        $targetUrl = 'https://noticias-antiguas-recicladas.com';

        Http::fake([
            'https://web.archive.org/cdx/search/cdx*' => Http::response([
                ['timestamp', 'original', 'mimetype', 'statuscode', 'digest'],
                ['20180415120000', $targetUrl, 'text/html', '200', 'DIGEST1'],
                ['20180515120000', $targetUrl, 'text/html', '200', 'DIGEST2'],
            ], 200),
        ]);

        /** @var ArchiveOrgService $service */
        $service = app(ConectorArchiveInterface::class);
        $resultado = $service->detectarDescontextualizacion($targetUrl);

        $this->assertTrue($resultado['descontextualizado']);
        $this->assertGreaterThan(1000, $resultado['antiguedad_dias']);
        $this->assertSame('CRITICO', $resultado['nivel_riesgo']);
        $this->assertStringContainsString('Alerta de descontextualización histórica', $resultado['diagnostico']);
        $this->assertNotNull($resultado['evidencia_wayback_url']);
    }

    public function test_detectar_descontextualizacion_con_noticia_reciente_no_activa_alerta(): void
    {
        $targetUrl = 'https://noticia-de-ayer.com';
        $timestampReciente = Carbon::now()->subDays(3)->format('YmdHis');

        Http::fake([
            'https://web.archive.org/cdx/search/cdx*' => Http::response([
                ['timestamp', 'original', 'mimetype', 'statuscode', 'digest'],
                [$timestampReciente, $targetUrl, 'text/html', '200', 'DIGEST1'],
            ], 200),
        ]);

        /** @var ArchiveOrgService $service */
        $service = app(ConectorArchiveInterface::class);
        $resultado = $service->detectarDescontextualizacion($targetUrl);

        $this->assertFalse($resultado['descontextualizado']);
        $this->assertSame('BAJO', $resultado['nivel_riesgo']);
        $this->assertStringContainsString('Registro histórico congruente', $resultado['diagnostico']);
    }

    public function test_endpoint_consultar_archive_requiere_autenticacion(): void
    {
        $response = $this->getJson('/api/v1/verificacion/archive/consultar?url=https://ejemplo.com');
        $response->assertStatus(401);
    }

    public function test_endpoint_consultar_archive_valida_url(): void
    {
        $response = $this->actingAs($this->usuario, 'sanctum')
            ->getJson('/api/v1/verificacion/archive/consultar?url=url_no_valida');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['url']);
    }

    public function test_endpoint_consultar_archive_retorna_200_con_datos(): void
    {
        $targetUrl = 'https://semana.com/noticia-relevante';

        Http::fake([
            'https://archive.org/wayback/available*' => Http::response([
                'url' => $targetUrl,
                'archived_snapshots' => [
                    'closest' => [
                        'status'    => '200',
                        'available' => true,
                        'url'       => 'http://web.archive.org/web/20200101000000/' . $targetUrl,
                        'timestamp' => '20200101000000',
                    ],
                ],
            ], 200),
            'https://web.archive.org/cdx/search/cdx*' => Http::response([
                ['timestamp', 'original', 'mimetype', 'statuscode', 'digest'],
                ['20200101000000', $targetUrl, 'text/html', '200', 'DIGEST1'],
            ], 200),
        ]);

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->getJson('/api/v1/verificacion/archive/consultar?url=' . urlencode($targetUrl));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'url',
                'disponible',
                'url_mas_cercana',
                'timestamp',
                'total_capturas',
                'capturas',
            ]);

        $this->assertTrue($response->json('disponible'));
    }

    public function test_endpoint_descontextualizacion_retorna_diagnostico(): void
    {
        $targetUrl = 'https://eltiempo.com/noticia-archivada';

        Http::fake([
            'https://web.archive.org/cdx/search/cdx*' => Http::response([
                ['timestamp', 'original', 'mimetype', 'statuscode', 'digest'],
                ['20170110120000', $targetUrl, 'text/html', '200', 'DIGEST1'],
            ], 200),
        ]);

        $response = $this->actingAs($this->usuario, 'sanctum')
            ->postJson('/api/v1/verificacion/archive/descontextualizacion', [
                'url' => $targetUrl,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'descontextualizado',
                'antiguedad_dias',
                'primera_captura',
                'ultima_captura',
                'total_capturas',
                'nivel_riesgo',
                'diagnostico',
                'evidencia_wayback_url',
            ]);

        $this->assertTrue($response->json('descontextualizado'));
        $this->assertSame('CRITICO', $response->json('nivel_riesgo'));
    }
}
