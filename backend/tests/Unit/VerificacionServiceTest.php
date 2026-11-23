<?php

namespace Tests\Unit;

use App\Contracts\AdaptadorApiIaInterface;
use App\Services\VerificacionService;
use Tests\TestCase;

/**
 * VerificacionServiceTest — Suite de Pruebas Unitarias del Motor DAGAZ
 *
 * Cubre los métodos públicos del VerificacionService:
 * - analizarNota(): CU01
 * - validarCalificacion(): CU02
 *
 * @package Tests\Unit
 */
class VerificacionServiceTest extends TestCase
{
    private VerificacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(VerificacionService::class);
    }

    // =========================================================================
    // CU01 — analizarNota()
    // =========================================================================

    public function test_analizar_nota_acepta_texto_valido(): void
    {
        $textoValido = 'Esta nota proporciona contexto adicional sobre la noticia verificada con fuentes primarias.';
        $this->assertTrue($this->service->analizarNota($textoValido));
    }

    public function test_analizar_nota_rechaza_texto_muy_corto(): void
    {
        $this->assertFalse($this->service->analizarNota('corto'));
    }

    public function test_analizar_nota_rechaza_texto_con_script_xss(): void
    {
        $textoMalicioso = 'Esta nota tiene <script>alert("xss")</script> contenido malicioso.';
        $this->assertFalse($this->service->analizarNota($textoMalicioso));
    }

    public function test_analizar_nota_rechaza_javascript_url(): void
    {
        $textoMalicioso = 'Haz clic aquí: javascript:void(document.cookie) para más info de la noticia falsa.';
        $this->assertFalse($this->service->analizarNota($textoMalicioso));
    }

    public function test_analizar_nota_rechaza_inyeccion_sql(): void
    {
        $textoSql = 'Noticia falsa UNION SELECT * FROM usuarios WHERE rol = admin DROP TABLE usuarios --';
        $this->assertFalse($this->service->analizarNota($textoSql));
    }

    public function test_analizar_nota_acepta_texto_con_html_basico_no_peligroso(): void
    {
        $texto = 'Según Reuters y AFP, la información sobre las elecciones colombianas es verificable en sus portales oficiales con más de 20 fuentes.';
        $this->assertTrue($this->service->analizarNota($texto));
    }

    // =========================================================================
    // CU02 — validarCalificacion()
    // =========================================================================

    public function test_validar_calificacion_acepta_valor_en_rango_minimo(): void
    {
        $this->assertTrue($this->service->validarCalificacion(0.0));
    }

    public function test_validar_calificacion_acepta_valor_en_rango_maximo(): void
    {
        $this->assertTrue($this->service->validarCalificacion(10.0));
    }

    public function test_validar_calificacion_acepta_valor_neutro(): void
    {
        $this->assertTrue($this->service->validarCalificacion(5.0));
        $this->assertTrue($this->service->validarCalificacion(7.5));
    }

    public function test_validar_calificacion_rechaza_valor_por_debajo_de_minimo(): void
    {
        $this->assertFalse($this->service->validarCalificacion(-1.0));
        $this->assertFalse($this->service->validarCalificacion(-100.0));
    }

    public function test_validar_calificacion_rechaza_valor_por_encima_de_maximo(): void
    {
        $this->assertFalse($this->service->validarCalificacion(10.1));
        $this->assertFalse($this->service->validarCalificacion(100.0));
    }

    // =========================================================================
    // Veredicto sin fuentes relacionadas en BD
    // =========================================================================

    public function test_determinar_veredicto_sin_fuentes_retorna_falso_y_mensaje_requerido(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('determinarVeredicto');
        $method->setAccessible(true);

        [$tipo, $explicacion] = $method->invoke(
            $this->service,
            20.0,
            collect([]),
            ['justificacion' => 'Análisis del modelo sin fuentes'],
            ['sesgo_score' => 0.1, 'tipo' => 'noticia']
        );

        $this->assertSame('FALSO', $tipo);
        $this->assertStringContainsString('no se encontró ninguna noticia o fuente relacionada a este tema', mb_strtolower($explicacion));
        $this->assertStringContainsString('este veredicto es posiblemente falso', mb_strtolower($explicacion));
        $this->assertStringContainsString('ningún organismo de control se ha pronunciado aún', mb_strtolower($explicacion));
        $this->assertStringContainsString('todavía no hay noticias del tema', mb_strtolower($explicacion));
    }

    public function test_calcular_score_sin_fuentes_retorna_rango_bajo(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('calcularScore');
        $method->setAccessible(true);

        $score = $method->invoke($this->service, collect([]), 80.0, 0.1, true);

        // Sin fuentes de control, debe estar acotado entre 10.0 y 25.0
        $this->assertGreaterThanOrEqual(10.0, $score);
        $this->assertLessThanOrEqual(25.0, $score);
    }

    public function test_contrastar_con_fuentes_envia_evidencia_documental_actualizada_al_adaptador_ia(): void
    {
        $textoCapturado = '';

        $adaptadorStub = new class($textoCapturado) implements AdaptadorApiIaInterface {
            public function __construct(private string &$capturado) {}
            public function analizarNaturaleza(string $texto): string { return 'noticia'; }
            public function detectarSesgoLenguaje(string $texto): float { return 0.0; }
            public function verificarCoherencia(string $texto): array
            {
                $this->capturado = $texto;
                return ['score' => 0.0, 'justificacion' => 'El TSE certificó la victoria de Lula.'];
            }
            public function interpretarPrompt(string $consulta): string { return ''; }
            public function descomponerAfirmaciones(string $texto): array { return [$texto]; }
            public function getNombreProveedor(): string { return 'StubIA'; }
        };

        $noticiasPorFuente = [
            'fuente-1' => [
                (object) [
                    'titulo'                    => 'TSE proclama reelección de Lula en Brasil',
                    'fragmento'                 => 'Flavio Bolsonaro no ganó las elecciones de octubre de 2026.',
                    'fecha_publicacion'         => '2026-10-31T22:00:00+00:00',
                    'coincidencias_especificas' => 2,
                    'coincidencias'             => 3,
                ],
            ],
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('contrastarConFuentes');
        $method->setAccessible(true);

        $resultado = $method->invoke(
            $this->service,
            $adaptadorStub,
            'Flavio Bolsonaro es presidente de Brasil',
            ['Flavio Bolsonaro es presidente de Brasil'],
            collect([]),
            '00000000-0000-0000-0000-000000000001',
            $noticiasPorFuente
        );

        $this->assertStringContainsString('EVIDENCIA DOCUMENTAL ACTUALIZADA EN LA BASE DE DATOS', $textoCapturado);
        $this->assertStringContainsString('TSE proclama reelección de Lula en Brasil', $textoCapturado);
        $this->assertSame('El TSE certificó la victoria de Lula.', $resultado['justificacion']);
    }
}
