<?php

namespace Tests\Feature;

use App\Models\Contenido;
use App\Models\Documento;
use App\Models\Fuente;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use App\Services\BusquedaSemanticaService;
use App\Services\VerificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * VerificacionesBackendFeatureTest — Suite Integral de Pruebas de Verificación Backend
 *
 * Valida los 7 requerimientos explicitados por el usuario:
 *  1. Debe recibir contenidos textuales.
 *  2. El contenido textual se obtiene para contrastar.
 *  3. Debe recibir contenido en imagen o audio, y pasarlo a texto para contrastar.
 *  4. Debe acceder correctamente en la base de datos para hacer consultas.
 *  5. Debe verificar en cada fuente, por cada noticia que hay relacionada al tema,
 *     guiándose por la metadata, para encontrar fuentes que respondan al texto.
 *  6. Debe hacer una verificación de hechos, contrastando con lo obtenido en las fuentes.
 *  7. Debe entregar un veredicto, citando las fuentes que usó para contrastar la información.
 *
 * @package Tests\Feature
 */
class VerificacionesBackendFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuario;
    private string $token;
    private Fuente $fuenteFinanzas;
    private Fuente $fuentePolitica;
    private Fuente $fuenteDeportes;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Crear usuario de prueba para autenticación opcional
        $this->usuario = Usuario::create([
            'email'         => 'verificador@dagaz.com',
            'password_hash' => Hash::make('secret123'),
            'rol'           => 'adulto',
        ]);
        $this->token = $this->usuario->createToken('test-token')->plainTextToken;

        // 2. Sembrar fuentes en el Grafo de Credibilidad de la BD
        $this->fuenteFinanzas = Fuente::create([
            'nombre'              => 'Portafolio Economía',
            'url_sitio'           => 'https://www.portafolio.co',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 92.00,
        ]);

        $this->fuentePolitica = Fuente::create([
            'nombre'              => 'ColombiaCheck Oficial',
            'url_sitio'           => 'https://colombiacheck.com',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 96.50,
        ]);

        $this->fuenteDeportes = Fuente::create([
            'nombre'              => 'ESPN Deportes',
            'url_sitio'           => 'https://espndeportes.espn.com',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 88.00,
        ]);
    }

    /**
     * Requerimiento 1 & 2:
     * - Debe recibir contenidos textuales.
     * - El contenido textual se obtiene para contrastar.
     */
    public function test_debe_recibir_contenido_textual_y_obtenerlo_para_contrastar(): void
    {
        $textoAfirmacion = 'El Banco de la República anunció un incremento del 0.5% en la tasa de interés para mitigar la inflación.';

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $textoAfirmacion,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'mensaje',
                'contenido' => ['id_contenido', 'tipo_entrada', 'contenido_original', 'estado'],
                'proceso'   => ['id_proceso', 'score_confiabilidad', 'proveedor_ia'],
                'veredicto' => ['id_veredicto', 'tipo_veredicto', 'nivel_confiabilidad', 'explicacion'],
                'fuentes',
                'categoria_metadata',
                'verificacion_hechos',
            ]);

        $this->assertEquals('TEXTO', $response->json('contenido.tipo_entrada'));
        $this->assertEquals($textoAfirmacion, $response->json('contenido.contenido_original'));
        $this->assertEquals('VERIFICADO', $response->json('contenido.estado'));

        // Se persiste en la BD raiden
        $this->assertDatabaseHas('contenidos', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $textoAfirmacion,
            'estado'             => 'VERIFICADO',
        ]);

        $this->assertDatabaseHas('procesos_verificacion', [
            'id_contenido' => $response->json('contenido.id_contenido'),
        ]);

        $this->assertDatabaseHas('veredictos', [
            'id_contenido' => $response->json('contenido.id_contenido'),
        ]);
    }

    /**
     * Requerimiento 3 (Parte A - Imagen):
     * - Debe recibir contenido en imagen, pasarlo a texto y contrastar.
     */
    public function test_debe_recibir_contenido_en_imagen_y_pasarlo_a_texto_para_contrastar(): void
    {
        $archivoImagen = UploadedFile::fake()->create(
            'noticia_viral_reforma_pensional_aprobada.png',
            120,
            'image/png'
        );

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada' => 'IMAGEN_OCR',
            'archivo'      => $archivoImagen,
        ]);

        $response->assertStatus(200);

        // Se extrajo texto y se usó como contenido original
        $contenidoOriginal = $response->json('contenido.contenido_original');
        $this->assertNotEmpty($contenidoOriginal);
        $this->assertGreaterThan(10, mb_strlen($contenidoOriginal));

        // En la BD el tipo de entrada se guarda de forma consistente
        $this->assertDatabaseHas('contenidos', [
            'tipo_entrada' => 'IMAGEN_OCR',
            'estado'       => 'VERIFICADO',
        ]);
    }

    /**
     * Requerimiento 3 (Parte B - Audio):
     * - Debe recibir contenido en audio, pasarlo a texto y contrastar.
     */
    public function test_debe_recibir_contenido_en_audio_y_pasarlo_a_texto_para_contrastar(): void
    {
        $archivoAudio = UploadedFile::fake()->createWithContent(
            'audio_declaracion_ministro_hacienda.mp3',
            'El ministro de hacienda comunica que no habrá alzas en los impuestos a la canasta familiar para el presente periodo.',
            'audio/mpeg'
        );

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada' => 'audio',
            'archivo'      => $archivoAudio,
        ]);

        $response->assertStatus(200);

        $contenidoOriginal = $response->json('contenido.contenido_original');
        $this->assertNotEmpty($contenidoOriginal);
        $this->assertStringContainsString('ministro', mb_strtolower($contenidoOriginal));

        // El veredicto fue generado
        $this->assertNotNull($response->json('veredicto.tipo_veredicto'));
        $this->assertNotEmpty($response->json('fuentes'));
    }

    /**
     * Requerimiento 4:
     * - Debe acceder correctamente en la base de datos para hacer consultas.
     */
    public function test_debe_acceder_correctamente_en_base_de_datos_para_hacer_consultas(): void
    {
        // Sembrar noticias en la tabla documentos vinculadas a fuentes
        $docFinanzas = Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $this->fuenteFinanzas->id_fuente,
            'categoria'         => 'Finanzas y Economía',
            'titulo'            => 'Tasas de interés suben por decisión de la junta directiva',
            'fragmento'         => 'El Banco de la República confirma incremento en tasas de interés y proyecta desaceleración de la inflación.',
            'url_articulo'      => 'https://www.portafolio.co/economia/tasas-inflacion-2026',
            'fecha_publicacion' => now()->subDays(2),
        ]);

        $docDeportes = Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $this->fuenteDeportes->id_fuente,
            'categoria'         => 'Deportes',
            'titulo'            => 'Final del campeonato de fútbol colombiano',
            'fragmento'         => 'El partido de vuelta de la liga de fútbol culminó con victoria del equipo local.',
            'url_articulo'      => 'https://espn.com/futbol/final-liga',
            'fecha_publicacion' => now()->subDay(),
        ]);

        $service = app(BusquedaSemanticaService::class);

        // Consultar directamente la BD vía BusquedaSemanticaService
        $resultado = $service->buscarNoticiasPorFuenteYMetadata(
            'El Banco de la República incrementa las tasas para contener la inflación económica.',
            'Finanzas y Economía'
        );

        $this->assertEquals('Finanzas y Economía', $resultado['categoria_metadata']);
        $this->assertTrue($resultado['fuentes_que_responden']->contains('id_fuente', $this->fuenteFinanzas->id_fuente));
        $this->assertArrayHasKey($this->fuenteFinanzas->id_fuente, $resultado['noticias_por_fuente']);

        $noticiasRecuperadas = $resultado['noticias_por_fuente'][$this->fuenteFinanzas->id_fuente];
        $this->assertNotEmpty($noticiasRecuperadas);
        $this->assertEquals($docFinanzas->titulo, $noticiasRecuperadas[0]->titulo);
    }

    /**
     * Requerimiento 5:
     * - Debe verificar en cada fuente, por cada noticia que hay relacionada al tema,
     *   guiándose por la metadata, para encontrar fuentes que respondan al texto.
     */
    public function test_debe_verificar_en_cada_fuente_por_cada_noticia_relacionada_guiandose_por_metadata(): void
    {
        // Crear 2 noticias relacionadas con Finanzas en dos fuentes distintas
        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $this->fuenteFinanzas->id_fuente,
            'categoria'         => 'Finanzas y Economía',
            'titulo'            => 'Alza del dólar y su impacto en la inflación local',
            'fragmento'         => 'Portafolio reporta que el dólar y la inflación mantienen presiones sobre el mercado cambiario y las tasas.',
            'url_articulo'      => 'https://www.portafolio.co/economia/dolar-inflacion',
            'fecha_publicacion' => now()->subDays(3),
        ]);

        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $this->fuentePolitica->id_fuente,
            'categoria'         => 'Finanzas y Economía',
            'titulo'            => 'ColombiaCheck analiza medidas económicas de hacienda',
            'fragmento'         => 'ColombiaCheck confirma que las cifras de inflación reportadas por el DANE corresponden a los índices oficiales.',
            'url_articulo'      => 'https://colombiacheck.com/chequeos/cifras-dolar-inflacion',
            'fecha_publicacion' => now()->subDays(1),
        ]);

        $texto = 'El dólar y la inflación alcanzan niveles históricos según informes de finanzas.';

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $texto,
        ]);

        $response->assertStatus(200);

        // Se guió por la metadata
        $this->assertEquals('Finanzas y Economía', $response->json('categoria_metadata'));

        // Se encontraron las fuentes que responden al texto
        $fuentesEnRespuesta = collect($response->json('fuentes'));
        $nombresFuentes = $fuentesEnRespuesta->pluck('nombre')->all();

        $this->assertContains('Portafolio Economía', $nombresFuentes);
        $this->assertContains('ColombiaCheck Oficial', $nombresFuentes);

        // Cada fuente evaluada tiene su noticia asociada y contrastada
        $hechos = $response->json('verificacion_hechos');
        $this->assertNotNull($hechos);
        $this->assertArrayHasKey('detalles', $hechos);
        $this->assertArrayHasKey($this->fuenteFinanzas->id_fuente, $hechos['detalles']);
        $this->assertArrayHasKey($this->fuentePolitica->id_fuente, $hechos['detalles']);
    }

    /**
     * Requerimiento 6:
     * - Debe hacer una verificación de hechos, contrastando con lo obtenido en las fuentes.
     */
    public function test_debe_hacer_verificacion_de_hechos_contrastando_con_lo_obtenido_en_fuentes(): void
    {
        // En la BD, ColombiaCheck tiene una noticia que desmiente explícitamente el rumor
        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $this->fuentePolitica->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Es falso que el presidente haya firmado decreto de expropiación',
            'fragmento'         => 'ColombiaCheck desmiente rumor falso que circula en redes: la presidencia no ha emitido ningún decreto de expropiación de tierras.',
            'url_articulo'      => 'https://colombiacheck.com/chequeos/desmentido-decreto-expropiacion',
            'fecha_publicacion' => now()->subDay(),
        ]);

        $rumorFalso = 'El presidente firmó ayer un nuevo decreto secreto para expropiar tierras a ganaderos según reportes de gobierno.';

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $rumorFalso,
        ]);

        $response->assertStatus(200);

        $hechos = $response->json('verificacion_hechos');
        $this->assertNotNull($hechos);

        // Verificó el hecho y detectó la refutación en la fuente
        $detallesFuente = $hechos['detalles'][$this->fuentePolitica->id_fuente];
        $this->assertEquals('REFUTA', $detallesFuente['estado_contraste']);
        $this->assertEquals(0.0, (float) $detallesFuente['concordancia']);

        // El veredicto refleja el resultado del contraste (FALSO)
        $tipoVeredicto = $response->json('veredicto.tipo_veredicto');
        $this->assertContains($tipoVeredicto, ['FALSO', 'ENGAÑOSO']);
    }

    /**
     * Requerimiento 7:
     * - Debe entregar un veredicto, citando las fuentes que usó para contrastar la información.
     */
    public function test_debe_entregar_veredicto_citando_las_fuentes_utilizadas_para_contrastar(): void
    {
        // Noticia de confirmación oficial en Portafolio
        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $this->fuenteFinanzas->id_fuente,
            'categoria'         => 'Finanzas y Economía',
            'titulo'            => 'Ministerio de Hacienda confirma aumento del salario mínimo',
            'fragmento'         => 'El Ministerio de Hacienda comunica y confirma que el incremento del salario mínimo rige desde el primer día del mes.',
            'url_articulo'      => 'https://www.portafolio.co/economia/salario-minimo-confirmado',
            'fecha_publicacion' => now()->subDays(2),
        ]);

        $afirmacion = 'El Ministerio de Hacienda confirma incremento en el salario mínimo y salario para los trabajadores.';

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $afirmacion,
        ]);

        $response->assertStatus(200);

        // 1. Entrega un veredicto válido según el esquema raiden
        $veredicto = $response->json('veredicto');
        $this->assertNotNull($veredicto['id_veredicto']);
        $this->assertContains($veredicto['tipo_veredicto'], ['VERDADERO', 'PARCIAL', 'ENGAÑOSO', 'FALSO', 'NO_VERIFICABLE']);
        $this->assertNotEmpty($veredicto['explicacion']);

        // 2. Cita las fuentes que usó para contrastar
        $fuentes = $response->json('fuentes');
        $this->assertNotEmpty($fuentes);

        $fuenteCitada = collect($fuentes)->firstWhere('nombre', 'Portafolio Economía');
        $this->assertNotNull($fuenteCitada);
        $this->assertNotEmpty($fuenteCitada['cita_contraste']);
        $this->assertStringContainsString('Portafolio Economía', $fuenteCitada['cita_contraste']);
        $this->assertStringContainsString('https://www.portafolio.co/economia/salario-minimo-confirmado', $fuenteCitada['cita_contraste']);

        // 3. Verifica en la tabla pivote de la base de datos (veredicto_fuentes)
        $idVeredicto = $veredicto['id_veredicto'];
        $this->assertDatabaseHas('veredicto_fuentes', [
            'id_veredicto' => $idVeredicto,
            'id_fuente'    => $this->fuenteFinanzas->id_fuente,
        ]);
    }

    /**
     * Test adicional:
     * - Afirmaciones absurdas, conspiranoicas o sin sustento alguno deben recibir score 0% y veredicto FALSO.
     */
    public function test_afirmaciones_absurdas_o_sin_fuentes_retornan_cero_confiabilidad_y_veredicto_falso(): void
    {
        $afirmacionAbsurda = 'El ministro de salud es un alien extraterrestre y el cielo de la ciudad es radioactivo.';

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $afirmacionAbsurda,
        ]);

        $response->assertStatus(200);
        $this->assertEquals('FALSO', $response->json('veredicto.tipo_veredicto'));
        $this->assertEquals(0.0, (float) $response->json('veredicto.nivel_confiabilidad'));
        $this->assertStringContainsString('0.0%', $response->json('veredicto.explicacion'));
    }

    public function test_flavio_bolsonaro_presidente_brasil_retorna_falso_y_conserva_cero_ante_disenso_de_consejo(): void
    {
        config()->set('dagaz.ia_proveedor', 'openrouter');
        config()->set('dagaz.openrouter.api_key', 'sk-or-v1-test');
        config()->set('dagaz.multi_modelo.habilitado', true);

        \Illuminate\Support\Facades\Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"score": 0.0, "justificación": "Según el TSE, Luiz Inácio Lula da Silva ganó en octubre de 2026 y Flavio Bolsonaro no fue electo presidente."}',
                        ],
                    ],
                ],
            ], 200),
            '*' => \Illuminate\Support\Facades\Http::response([], 404),
        ]);

        // Mockear consejo devolviendo 1 voto disidente sin quórum de calibración
        $this->mock(\App\Services\MultiModeloHarnessService::class, function ($mock) {
            $mock->shouldReceive('evaluarConConsejo')->once()->andReturn([
                'habilitado'           => true,
                'total_consultados'    => 2,
                'total_respondieron'   => 1,
                'veredicto_propuesto'  => 'FALSO',
                'veredicto_consenso'   => 'NO_VERIFICABLE',
                'porcentaje_consenso'  => 100.0,
                'confianza_promedio'   => 80.0,
                'calibracion_aplicada' => false,
                'veredicto_calibrado'  => 'FALSO',
                'resumen_consenso'     => 'El Agente Evaluador analizó la afirmación y sugirió la calificación NO_VERIFICABLE. Se mantiene el veredicto preliminar FALSO.',
                'fuentes_evidencias'   => [],
            ]);
        });

        $fuenteEfe = Fuente::create([
            'nombre'              => 'Agencia EFE',
            'url_sitio'           => 'https://efe.com',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 97.00,
        ]);

        $fuenteBoyaca = Fuente::create([
            'nombre'              => 'Boyacá 7 Días',
            'url_sitio'           => 'https://boyaca7dias.com.co',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 82.00,
        ]);

        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $fuenteEfe->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Tribunal Superior Eleitoral proclama reelección de Lula en Brasil y certifica que Flavio Bolsonaro no ganó en octubre de 2026',
            'fragmento'         => 'Brasilia (EFE).- El TSE oficializó los resultados definitivos de las elecciones presidenciales de Brasil celebradas en octubre de 2026, confirmando la victoria de Luiz Inácio Lula da Silva y certificando que Flavio Bolsonaro no ganó.',
            'url_articulo'      => 'https://efe.com/politica/elecciones-brasil-2026-tse-resultados-oficiales',
            'fecha_publicacion' => now()->subDays(5),
        ]);

        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $fuenteBoyaca->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'La casa blanca confirma que trump llamo a bolsonaro para felicitarle',
            'fragmento'         => 'La casa blanca confirma que trump llamo a bolsonaro para felicitarle.',
            'url_articulo'      => 'https://boyaca7dias.com.co/bolsonaro-trump-felicitacion',
            'fecha_publicacion' => now()->subDays(10),
        ]);

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Flavio Bolsonaro es presidente de Brasil',
        ]);

        $response->assertStatus(200);
        $this->assertSame('FALSO', $response->json('veredicto.tipo_veredicto'));
        $this->assertSame(0.0, (float) $response->json('veredicto.nivel_confiabilidad'));
        $this->assertStringContainsString('0.0%', $response->json('veredicto.explicacion'));
        $this->assertStringContainsString('Tribunal Superior Eleitoral', $response->json('veredicto.explicacion'));
        $this->assertStringContainsString('Agencia EFE', $response->json('veredicto.explicacion'));
        $this->assertSame('REFUTA', $response->json("verificacion_hechos.detalles.{$fuenteEfe->id_fuente}.estado_contraste"));
    }

    public function test_elecciones_brasil_perdidas_por_flavio_bolsonaro_retorna_verdadero(): void
    {
        config()->set('dagaz.ia_proveedor', 'openrouter');
        config()->set('dagaz.openrouter.api_key', 'sk-or-v1-test');
        config()->set('dagaz.multi_modelo.habilitado', false);

        \Illuminate\Support\Facades\Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => '{"score": 0.95, "justificacion": "Las elecciones de octubre de 2026 ya concluyeron y Flavio Bolsonaro fue derrotado."}',
                        ],
                    ],
                ],
            ], 200),
            '*' => \Illuminate\Support\Facades\Http::response([], 404),
        ]);

        $fuenteEfe = Fuente::create([
            'nombre'              => 'Agencia EFE',
            'url_sitio'           => 'https://efe.com',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 97.00,
        ]);

        Documento::create([
            'id_documento'      => (string) Str::uuid(),
            'fuente_id'         => $fuenteEfe->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Tribunal Superior Eleitoral proclama reelección de Lula en Brasil y certifica que Flavio Bolsonaro no ganó en octubre de 2026',
            'fragmento'         => 'El TSE oficializó los resultados de las elecciones celebradas en octubre de 2026 certificando que Flavio Bolsonaro no ganó.',
            'url_articulo'      => 'https://efe.com/politica/elecciones-brasil-2026-tse-resultados-oficiales',
            'fecha_publicacion' => now()->subDays(5),
        ]);

        $response = $this->postJson('/api/v1/verificacion', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Las elecciones de Brasil ya sucedieron este 25 de octubre y las perdió Flavio Bolsonaro',
        ]);

        $response->assertStatus(200);
        $this->assertSame('VERDADERO', $response->json('veredicto.tipo_veredicto'));
        $this->assertGreaterThanOrEqual(75.0, (float) $response->json('veredicto.nivel_confiabilidad'));
        $this->assertSame('CONFIRMA', $response->json("verificacion_hechos.detalles.{$fuenteEfe->id_fuente}.estado_contraste"));
    }

    public function test_afirmacion_refutada_por_ia_con_fuentes_solo_tangenciales_retorna_falso_y_no_enganoso(): void
    {
        $originalConfig = config('dagaz');

        try {
            config([
                'dagaz.ia_proveedor'            => 'gemini',
                'dagaz.gemini.api_key'          => 'AIza-test-key',
                'dagaz.gemini.modelo'           => 'gemini-3.5-flash-lite',
                'dagaz.multi_modelo.habilitado' => false,
            ]);

            // Simular fuentes del grafo con coincidencias meramente tangenciales sobre el nombre propio (CONTEXTUALIZA)
            $fuenteSilla = Fuente::create([
                'nombre'              => 'La Silla Vacía',
                'url_sitio'           => 'https://www.lasillavacia.com',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 98.00,
            ]);

            $fuenteMaldita = Fuente::create([
                'nombre'              => 'Maldita.es',
                'url_sitio'           => 'https://maldita.es',
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 95.00,
            ]);

            Documento::create([
                'id_documento'      => (string) Str::uuid(),
                'fuente_id'         => $fuenteSilla->id_fuente,
                'categoria'         => 'Gobierno y Política',
                'titulo'            => 'Así funcionó el músculo mediático de Negre en la campaña abelardista en Colombia',
                'fragmento'         => 'Artículo sobre estrategia digital de Abelardo de la Espriella en Colombia.',
                'url_articulo'      => 'https://www.lasillavacia.com/detector-de-mentiras/abelardista',
                'fecha_publicacion' => now()->subDays(2),
            ]);

            Documento::create([
                'id_documento'      => (string) Str::uuid(),
                'fuente_id'         => $fuenteMaldita->id_fuente,
                'categoria'         => 'Gobierno y Política',
                'titulo'            => 'Análisis de campañas políticas recientes en la región',
                'fragmento'         => 'Mención secundaria a la campaña de Abelardo en redes sociales.',
                'url_articulo'      => 'https://maldita.es/politica/campana-abelardo',
                'fecha_publicacion' => now()->subDays(2),
            ]);

            \Illuminate\Support\Facades\Http::fake([
                'https://generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => '{"score": 0.0, "justificacion": "La afirmación carece de sustento factual y no corresponde a un hecho verificable en la evidencia documental."}',
                            ]],
                        ],
                    ]],
                ], 200),
            ]);

            $response = $this->postJson('/api/v1/verificacion', [
                'tipo_entrada'       => 'TEXTO',
                'contenido_original' => 'Abelardo le hace fracking imaginario a polo polo en el congreso',
            ]);

            $response->assertStatus(200);
            $this->assertSame('FALSO', $response->json('veredicto.tipo_veredicto'));
            $this->assertSame(0.0, (float) $response->json('veredicto.nivel_confiabilidad'));
            $explicacion = (string) $response->json('veredicto.explicacion');
            $this->assertStringContainsString('carece de sustento factual', $explicacion);

            // Ni la explicación ni las fuentes asociadas deben arrastrar noticias tangenciales sin relación
            $this->assertStringNotContainsString('(CONTEXTUALIZA):', $explicacion);
            $this->assertStringNotContainsString('campaña abelardista', $explicacion);
            $this->assertStringNotContainsString('Maldita.es', $explicacion);

            $nombresFuentes = collect($response->json('fuentes'))->pluck('nombre')->all();
            $this->assertNotContains('La Silla Vacía', $nombresFuentes);
            $this->assertNotContains('Maldita.es', $nombresFuentes);

            $idVeredicto = $response->json('veredicto.id_veredicto');
            $this->assertDatabaseMissing('veredicto_fuentes', [
                'id_veredicto' => $idVeredicto,
                'id_fuente'    => $fuenteSilla->id_fuente,
            ]);
            $this->assertDatabaseMissing('veredicto_fuentes', [
                'id_veredicto' => $idVeredicto,
                'id_fuente'    => $fuenteMaldita->id_fuente,
            ]);
        } finally {
            config(['dagaz' => $originalConfig]);
        }
    }
}
