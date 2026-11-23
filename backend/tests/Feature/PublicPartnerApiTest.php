<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contenido;
use App\Models\Fuente;
use App\Models\LogComunicadorApi;
use App\Models\ProcesoVerificacion;
use App\Models\Veredicto;
use App\Services\PartnerApiService;
use App\Services\VerificacionService;
use Database\Seeders\FuentesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * PublicPartnerApiTest — Suite de Pruebas para API Pública de Redacciones Socias
 *
 * TSK-4.2 (Sprint 4 / REQ-16): Endpoints REST para salas de prensa aliadas con API Key.
 */
class PublicPartnerApiTest extends TestCase
{
    use RefreshDatabase;

    private string $validKey = 'dagaz_partner_test_key_secret_12345';
    private string $invalidKey = 'dagaz_partner_invalid_fake_key_99999';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(FuentesSeeder::class);
    }

    public function test_rechazo_sin_cabecera_api_key_retorna_401(): void
    {
        $response = $this->postJson('/api/v1/public/verificar', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Afirmación de prueba enviada sin API Key autenticada.',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error', 'unauthorized')
            ->assertJsonStructure(['error', 'mensaje']);
    }

    public function test_rechazo_con_api_key_invalida_retorna_403(): void
    {
        $response = $this->withHeader('X-API-KEY', $this->invalidKey)
            ->postJson('/api/v1/public/verificar', [
                'tipo_entrada'       => 'TEXTO',
                'contenido_original' => 'Afirmación de prueba con API Key no registrada.',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_rechazo_con_api_key_inactiva_retorna_403(): void
    {
        $inactiveKey = 'dagaz_partner_inactive_key';

        Config::set('dagaz.partners.keys.' . $inactiveKey, [
            'id_socio'          => 'socio-inactivo',
            'nombre'            => 'Socio Revocado Temporalmente',
            'rate_limit_minute' => 60,
            'activo'            => false,
            'permisos'          => ['verificar'],
        ]);

        $response = $this->withHeader('X-API-KEY', $inactiveKey)
            ->getJson('/api/v1/public/health');

        $response->assertStatus(403);
    }

    public function test_endpoint_health_con_api_key_valida(): void
    {
        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->getJson('/api/v1/public/health');

        $response->assertStatus(200)
            ->assertHeader('X-Partner-Id', 'socio-test')
            ->assertJsonPath('status', 'operational')
            ->assertJsonPath('servicio', 'DAGAZ Public Partner API v1')
            ->assertJsonStructure(['status', 'servicio', 'socio_activo', 'timestamp', 'version', 'rate_limit', 'sla']);
    }

    public function test_listar_fuentes_grafo_desde_api_publica(): void
    {
        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->getJson('/api/v1/public/fuentes');

        $response->assertStatus(200)
            ->assertHeader('X-Partner-Id', 'socio-test')
            ->assertJsonPath('version_api', 'v1.0')
            ->assertJsonStructure([
                'version_api',
                'socio' => ['id_socio', 'nombre'],
                'total',
                'fuentes' => [
                    '*' => [
                        'id_fuente',
                        'nombre',
                        'url_sitio',
                        'tipo_fuente',
                        'clasificacion',
                        'nivel',
                        'nivel_etiqueta',
                        'indice_credibilidad',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(80, $response->json('total'));
    }

    public function test_listar_fuentes_filtradas_por_nivel(): void
    {
        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->getJson('/api/v1/public/fuentes?nivel=1');

        $response->assertStatus(200);
        $fuentes = $response->json('fuentes');

        $this->assertNotEmpty($fuentes);
        foreach ($fuentes as $f) {
            $this->assertSame(1, $f['nivel']);
        }
    }

    public function test_buscar_fuente_por_texto_en_api_publica(): void
    {
        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->getJson('/api/v1/public/fuentes?buscar=Espectador');

        $response->assertStatus(200);
        $fuentes = $response->json('fuentes');

        $this->assertNotEmpty($fuentes);
        $this->assertStringContainsString('Espectador', $fuentes[0]['nombre']);
    }

    public function test_verificar_contenido_exitoso_para_redaccion_socia(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'El Ministerio de Salud confirma nuevas medidas preventivas en Colombia.',
            'estado'             => 'VERIFICADO',
        ]);

        $proceso = ProcesoVerificacion::create([
            'id_contenido'        => $contenido->id_contenido,
            'score_confiabilidad' => 88.5,
        ]);

        $idVeredicto = (string) Str::uuid();

        $mockVerificacion = $this->createMock(VerificacionService::class);
        $mockVerificacion->expects($this->once())
            ->method('procesarContenido')
            ->willReturn([
                'contenido' => [
                    'id_contenido'       => $contenido->id_contenido,
                    'tipo_entrada'       => 'TEXTO',
                    'contenido_original' => 'El Ministerio de Salud confirma nuevas medidas preventivas en Colombia.',
                    'estado'             => 'VERIFICADO',
                ],
                'proceso' => [
                    'id_proceso'          => $proceso->id_proceso,
                    'score_confiabilidad' => 88.5,
                    'proveedor_ia'        => 'mock',
                ],
                'veredicto' => [
                    'id_veredicto'        => $idVeredicto,
                    'tipo_veredicto'      => 'VERDADERO',
                    'nivel_confiabilidad' => 88.5,
                    'explicacion'         => 'Verificado exitosamente con fuentes oficiales.',
                    'fecha_emision'       => now()->toIso8601String(),
                ],
                'fuentes' => [
                    [
                        'nombre'              => 'Ministerio de Salud y Protección Social',
                        'url_sitio'           => 'https://www.minsalud.gov.co',
                        'tipo_fuente'         => 'OFICIAL',
                        'indice_credibilidad' => 97.0,
                        'peso_en_veredicto'   => 0.9,
                        'cita_contraste'      => 'Resolución oficial verificada.',
                    ],
                ],
            ]);
        $this->app->instance(VerificacionService::class, $mockVerificacion);

        $payload = [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'El Ministerio de Salud confirma nuevas medidas preventivas en Colombia.',
        ];

        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->postJson('/api/v1/public/verificar', $payload);

        $response->assertStatus(200)
            ->assertHeader('X-Partner-Id', 'socio-test')
            ->assertJsonPath('version_api', 'v1.0')
            ->assertJsonPath('socio.id_socio', 'socio-test')
            ->assertJsonStructure([
                'version_api',
                'socio' => ['id_socio', 'nombre'],
                'solicitud' => [
                    'id_contenido',
                    'id_proceso',
                    'tipo_entrada',
                    'estado',
                    'timestamp',
                ],
                'veredicto' => [
                    'tipo_veredicto',
                    'nivel_confiabilidad',
                    'explicacion',
                ],
                'fuentes_contraste',
            ]);

        $this->assertDatabaseHas('logs_comunicador_api', [
            'servicio'       => 'PartnerAPI:socio-test',
            'tipo_operacion' => 'verificacion_socio',
        ]);
    }

    public function test_consultar_veredicto_existente_por_id(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Afirmación previa verificada en sala de redacción.',
            'estado'             => 'VERIFICADO',
        ]);

        $proceso = ProcesoVerificacion::create([
            'id_contenido'        => $contenido->id_contenido,
            'score_confiabilidad' => 88.50,
        ]);

        Veredicto::create([
            'id_contenido'        => $contenido->id_contenido,
            'id_proceso'          => $proceso->id_proceso,
            'tipo_veredicto'      => 'VERDADERO',
            'nivel_confiabilidad' => 88.50,
            'explicacion'         => 'Verificado formalmente como verídico tras contraste.',
        ]);

        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->getJson("/api/v1/public/veredictos/{$contenido->id_contenido}");

        $response->assertStatus(200)
            ->assertJsonPath('contenido.id_contenido', $contenido->id_contenido)
            ->assertJsonPath('veredicto.tipo_veredicto', 'VERDADERO');
    }

    public function test_consultar_veredicto_inexistente_retorna_404(): void
    {
        $uuidFantasma = '00000000-0000-0000-0000-000000000000';

        $response = $this->withHeader('X-API-KEY', $this->validKey)
            ->getJson("/api/v1/public/veredictos/{$uuidFantasma}");

        $response->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_autenticacion_alternativa_via_header_authorization_api_key(): void
    {
        $response = $this->withHeader('Authorization', 'Api-Key ' . $this->validKey)
            ->getJson('/api/v1/public/health');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'operational');
    }
}
