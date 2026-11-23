<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Contracts\AdaptadorApiIaInterface;
use App\Contracts\ServicioInterlingueInterface;
use App\Models\LogComunicadorApi;
use App\Models\Usuario;
use App\Services\ServicioInterlingue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * InterlingueTest — Suite de Pruebas para Soporte de Recuperación Interlingüe
 * TSK-4.4 & REQ-19: Detección bilingüe, traducción preservativa y detección de distorsión semántica.
 */
class InterlingueTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = Usuario::create([
            'email'         => 'adulto.interlingue@dagaz.com',
            'password_hash' => bcrypt('Password123!'),
            'rol'           => 'adulto',
        ]);

        $this->token = $this->usuario->createToken('test-interlingue-token')->plainTextToken;
    }

    public function test_detectar_idioma_espanol(): void
    {
        /** @var ServicioInterlingueInterface $service */
        $service = app(ServicioInterlingueInterface::class);

        $texto = 'El ministerio de salud de Colombia anunció nuevas medidas sanitarias para prevenir la propagación de enfermedades.';
        $resultado = $service->detectarIdioma($texto);

        $this->assertSame('es', $resultado['idioma_detectado']);
        $this->assertFalse($resultado['es_mixto']);
        $this->assertGreaterThan(0.6, $resultado['confianza']);
        $this->assertGreaterThan(5, $resultado['conteo_palabras']);
    }

    public function test_detectar_idioma_ingles(): void
    {
        /** @var ServicioInterlingueInterface $service */
        $service = app(ServicioInterlingueInterface::class);

        $texto = 'The health ministry announced several critical guidelines to prevent the outbreak according to international scientists and researchers.';
        $resultado = $service->detectarIdioma($texto);

        $this->assertSame('en', $resultado['idioma_detectado']);
        $this->assertFalse($resultado['es_mixto']);
        $this->assertGreaterThan(0.6, $resultado['confianza']);
    }

    public function test_detectar_idioma_mixto(): void
    {
        /** @var ServicioInterlingueInterface $service */
        $service = app(ServicioInterlingueInterface::class);

        $texto = 'Breaking news this morning: El nuevo update confirma el fake news spreading everywhere en las redes sociales de Colombia.';
        $resultado = $service->detectarIdioma($texto);

        $this->assertTrue($resultado['es_mixto']);
        $this->assertSame('mixto', $resultado['idioma_detectado']);
    }

    public function test_traducir_con_mock_ia(): void
    {
        $mockIa = $this->createMock(AdaptadorApiIaInterface::class);
        $mockIa->method('interpretarPrompt')
            ->willReturn('Estudio revela nueva evidencia sobre el clima en el mundo.');

        $service = new ServicioInterlingue(adaptadorIa: $mockIa);

        $res = $service->traducir('Study reveals new evidence on world climate.', 'es', 'en');

        $this->assertSame('es', $res['idioma_destino']);
        $this->assertSame('ia_driver', $res['metodo']);
        $this->assertNotEmpty($res['texto_traducido']);
    }

    public function test_traducir_fallback_heuristico_cuando_ai_falla(): void
    {
        $mockIa = $this->createMock(AdaptadorApiIaInterface::class);
        $mockIa->method('interpretarPrompt')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $service = new ServicioInterlingue(adaptadorIa: $mockIa);

        $res = $service->traducir('urgent alert about vaccine and fake news', 'es', 'en');

        $this->assertSame('es', $res['idioma_destino']);
        $this->assertSame('glosario_heuristico', $res['metodo']);
        $this->assertStringContainsString('vacuna', mb_strtolower($res['texto_traducido']));
    }

    public function test_analizar_distorsion_detecta_sensacionalismo_y_atribucion_falsa(): void
    {
        /** @var ServicioInterlingueInterface $service */
        $service = app(ServicioInterlingueInterface::class);

        $origenEn = 'A small preliminary laboratory trial showed that some cells reacted under specific experimental conditions.';
        $traducidoEs = 'URGENTE: La OMS confirma la cura definitiva y milagrosa del cáncer, esto es un escándalo mundial.';

        $analisis = $service->analizarDistorsionTraduccion($origenEn, $traducidoEs);

        $this->assertTrue($analisis['distorsion_detectada']);
        $this->assertTrue($analisis['sensacionalismo_agregado']);
        $this->assertTrue($analisis['distorsion_certeza']);
        $this->assertTrue($analisis['atribucion_institucional_falsa']);
        $this->assertGreaterThan(0.3, $analisis['score_distorsion']);
        $this->assertNotEmpty($analisis['terminos_alarmistas_agregados']);
    }

    public function test_analizar_distorsion_sin_distorsion_fiel(): void
    {
        /** @var ServicioInterlingueInterface $service */
        $service = app(ServicioInterlingueInterface::class);

        $origenEn = 'The local hospital reported ten new patients admitted yesterday during the heavy storm.';
        $traducidoEs = 'El hospital local reportó diez nuevos pacientes admitidos ayer durante la fuerte tormenta.';

        $analisis = $service->analizarDistorsionTraduccion($origenEn, $traducidoEs);

        $this->assertFalse($analisis['distorsion_detectada']);
        $this->assertFalse($analisis['sensacionalismo_agregado']);
        $this->assertFalse($analisis['atribucion_institucional_falsa']);
        $this->assertLessThan(0.3, $analisis['score_distorsion']);
    }

    public function test_generar_consultas_interlingues(): void
    {
        /** @var ServicioInterlingueInterface $service */
        $service = app(ServicioInterlingueInterface::class);

        $textoEs = 'Afirman que la OMS descubrió un nuevo virus peligroso en agua potable.';
        $consultas = $service->generarConsultasInterlingues($textoEs);

        $this->assertIsArray($consultas);
        $this->assertArrayHasKey('consulta_principal_en', $consultas);
        $this->assertArrayHasKey('consultas_alternativas', $consultas);
        $this->assertNotEmpty($consultas['consultas_alternativas']);
        $this->assertStringContainsString('fact check', $consultas['consulta_principal_en']);

        $consultasUnidas = implode(' ', $consultas['consultas_alternativas']);
        $this->assertTrue(
            str_contains($consultasUnidas, 'Reuters') ||
            str_contains($consultasUnidas, 'BBC') ||
            str_contains($consultasUnidas, 'WHO')
        );
    }

    public function test_endpoint_detectar_idioma_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/verificacion/interlingue/detectar', [
                'texto' => 'Este es un reporte informativo completamente redactado en idioma español.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('deteccion.idioma_detectado', 'es')
            ->assertJsonPath('deteccion.es_mixto', false);
    }

    public function test_endpoint_traducir_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/verificacion/interlingue/traducir', [
                'texto'          => 'The government announces contingency plan.',
                'idioma_origen'  => 'en',
                'idioma_destino' => 'es',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'resultado' => [
                    'texto_traducido',
                    'idioma_origen',
                    'idioma_destino',
                    'metodo',
                ],
            ]);
    }

    public function test_endpoint_distorsion_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/verificacion/interlingue/distorsion', [
                'texto_origen'    => 'Possible mild effect detected in lab.',
                'texto_traducido' => 'URGENTE: La OMS confirma la cura milagrosa del cáncer.',
                'idioma_origen'   => 'en',
                'idioma_destino'  => 'es',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('analisis.distorsion_detectada', true)
            ->assertJsonPath('analisis.sensacionalismo_agregado', true);
    }

    public function test_auditoria_logs_comunicador_api_registra_interlingue(): void
    {
        $mockIa = $this->createMock(AdaptadorApiIaInterface::class);
        $mockIa->method('interpretarPrompt')
            ->willThrowException(new \RuntimeException('AI Service Down'));

        $service = new ServicioInterlingue(adaptadorIa: $mockIa);
        $service->traducir('Health report confirmed', 'es', 'en');

        $this->assertDatabaseHas('logs_comunicador_api', [
            'servicio'         => 'Interlingue',
            'codigo_respuesta' => 500,
        ]);
    }
}
