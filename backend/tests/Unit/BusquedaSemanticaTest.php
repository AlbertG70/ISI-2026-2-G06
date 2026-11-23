<?php

namespace Tests\Unit;

use App\Models\Documento;
use App\Models\Fuente;
use App\Services\BusquedaSemanticaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusquedaSemanticaTest extends TestCase
{
    use RefreshDatabase;

    private Fuente $fuenteOficial;
    private BusquedaSemanticaService $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        $this->servicio = new BusquedaSemanticaService();

        $this->fuenteOficial = Fuente::create([
            'nombre'              => 'Ministerio de Salud y Protección Social',
            'url_sitio'           => 'https://www.minsalud.gov.co',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 99.00,
        ]);
    }

    public function test_constante_dimension_vectorial_es_384(): void
    {
        $this->assertSame(384, Documento::DIMENSION_VECTORIAL);
        $this->assertSame(384, BusquedaSemanticaService::DIMENSION);
    }

    public function test_creacion_documento_indexado_con_relacion_fuente(): void
    {
        $doc = Documento::create([
            'fuente_id'         => $this->fuenteOficial->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Boletín Epidemiológico sobre Vacunación en Colombia',
            'fragmento'         => 'El Ministerio de Salud confirma que las vacunas son seguras y salvan vidas en el territorio nacional.',
            'url_articulo'      => 'https://www.minsalud.gov.co/boletin-101',
            'fecha_publicacion' => now()->subDays(5),
        ]);

        $this->assertNotEmpty($doc->id_documento);
        $this->assertEquals($this->fuenteOficial->id_fuente, $doc->fuente->id_fuente);
        $this->assertTrue($this->fuenteOficial->documentos->contains('id_documento', $doc->id_documento));
    }

    public function test_busqueda_evidencia_por_texto_encuentra_documentos(): void
    {
        Documento::create([
            'fuente_id'    => $this->fuenteOficial->id_fuente,
            'categoria'    => 'Gobierno y Política',
            'titulo'       => 'Medidas de contingencia y prevención médica',
            'fragmento'    => 'Protocolos de atención médica emitidos por las autoridades sanitarias.',
            'url_articulo' => 'https://www.minsalud.gov.co/protocolo-covid',
        ]);

        $resultados = $this->servicio->buscarEvidenciaPorTexto('prevención médica sanitaria');

        $this->assertNotEmpty($resultados);
        $this->assertStringContainsString('prevención', $resultados->first()->titulo);
    }

    public function test_busqueda_por_vector_retorna_coleccion_estructurada(): void
    {
        Documento::create([
            'fuente_id'    => $this->fuenteOficial->id_fuente,
            'categoria'    => 'Gobierno y Política',
            'titulo'       => 'Reporte Oficial de Empleo y Estadísticas',
            'fragmento'    => 'Cifras verificadas de empleo y ocupación.',
            'url_articulo' => 'https://www.minsalud.gov.co/empleo-2026',
        ]);

        // Simulación de vector de 384 dimensiones
        $vectorDummy = array_fill(0, 384, 0.05);

        $candidatos = $this->servicio->buscarPorVector(
            vector: $vectorDummy,
            limite: 5,
            fuenteIds: [$this->fuenteOficial->id_fuente]
        );

        $this->assertCount(1, $candidatos);
        $this->assertNotNull($candidatos->first()->similitud);
    }

    public function test_extraer_citas_para_fuentes_asigna_citas_textuales(): void
    {
        Documento::create([
            'fuente_id'    => $this->fuenteOficial->id_fuente,
            'categoria'    => 'Gobierno y Política',
            'titulo'       => 'Pronunciamiento oficial sobre seguridad ciudadana',
            'fragmento'    => 'El gobierno garantiza la normalidad del orden público en todas las regiones.',
            'url_articulo' => 'https://www.minsalud.gov.co/orden-publico',
        ]);

        $fuentes = Fuente::where('id_fuente', $this->fuenteOficial->id_fuente)->get();
        $citas = $this->servicio->extraerCitasParaFuentes('seguridad ciudadana y orden público', $fuentes);

        $this->assertArrayHasKey($this->fuenteOficial->id_fuente, $citas);
        $this->assertNotNull($citas[$this->fuenteOficial->id_fuente]);
        $this->assertStringContainsString('Ministerio de Salud', $citas[$this->fuenteOficial->id_fuente]);
    }

    public function test_extraer_palabras_clave_con_terminos_numericos_retorna_strings(): void
    {
        $palabras = $this->servicio->extraerPalabrasClave('elecciones presidenciales de 2026 en colombia');

        $this->assertContains('2026', $palabras);
        foreach ($palabras as $p) {
            $this->assertIsString($p);
        }
    }

    public function test_buscar_noticias_por_fuente_y_metadata_maneja_anos_y_numeros(): void
    {
        Documento::create([
            'fuente_id'    => $this->fuenteOficial->id_fuente,
            'categoria'    => 'Gobierno y Política',
            'titulo'       => 'Elecciones de 2026 y previsiones electorales',
            'fragmento'    => 'Resultados preliminares y previsiones sobre los comicios del año 2026.',
            'url_articulo' => 'https://www.minsalud.gov.co/elecciones-2026',
        ]);

        $resultado = $this->servicio->buscarNoticiasPorFuenteYMetadata('elecciones generales de 2026');

        $this->assertIsArray($resultado);
        $this->assertArrayHasKey('fuentes_que_responden', $resultado);
        $this->assertArrayHasKey('noticias_por_fuente', $resultado);
    }

    public function test_contrastar_hecho_institucional_refuta_flavio_bolsonaro_presidente_brasil(): void
    {
        $sinTilde = $this->servicio->contrastarHechoInstitucional('Flavio Bolsonaro es presidente de Brasil');
        $conTilde = $this->servicio->contrastarHechoInstitucional('Flávio Bolsonaro es presidente de Brasil');

        foreach ([$sinTilde, $conTilde] as $res) {
            $this->assertNotNull($res);
            $this->assertFalse($res['es_confirmacion']);
            $this->assertTrue($res['es_refutacion']);
            $this->assertSame(0.0, $res['score']);
            $this->assertSame('Luiz Inácio Lula da Silva', $res['titular_oficial']);
            $this->assertStringContainsString('25 de octubre de 2026', $res['cita']);
        }
    }

    public function test_contrastar_hecho_institucional_confirma_derrota_bolsonaro_y_maneja_negaciones(): void
    {
        $casosConfirmados = [
            'Flavio Bolsonaro no ganó las elecciones de Brasil en 2026',
            'Las elecciones de Brasil ya sucedieron este 25 de octubre y las perdió Flavio Bolsonaro',
            'Luiz Inácio Lula da Silva fue reelegido presidente de Brasil y Flavio Bolsonaro perdió',
            'Es falso que Flavio Bolsonaro es presidente de Brasil',
        ];

        foreach ($casosConfirmados as $afirmacion) {
            $res = $this->servicio->contrastarHechoInstitucional($afirmacion);
            $this->assertNotNull($res, "Se esperaba match institucional para: {$afirmacion}");
            $this->assertTrue($res['es_confirmacion'], "Debió confirmar: {$afirmacion}");
            $this->assertFalse($res['es_refutacion'], "No debió refutar: {$afirmacion}");
            $this->assertSame(95.0, $res['score']);
        }

        $casosRefutados = [
            'Lula da Silva no es presidente de Brasil',
            'Es falso que Lula sea presidente de Brasil',
        ];

        foreach ($casosRefutados as $afirmacion) {
            $res = $this->servicio->contrastarHechoInstitucional($afirmacion);
            $this->assertNotNull($res);
            $this->assertFalse($res['es_confirmacion']);
            $this->assertTrue($res['es_refutacion']);
            $this->assertSame(0.0, $res['score']);
        }

        // Contexto histórico 2019-2022 no debe activar erróneamente el registro de 2026
        $historico = $this->servicio->contrastarHechoInstitucional('Jair Bolsonaro fue presidente de Brasil entre 2019 y 2022');
        $this->assertNull($historico);
    }

    public function test_verificar_hechos_bloquea_confirma_en_coincidencia_parcial_y_noticia_antigua(): void
    {
        $fuenteEfe = Fuente::create([
            'nombre'              => 'Agencia EFE',
            'url_sitio'           => 'https://efe.com',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 97.00,
        ]);

        $fuenteRegional = Fuente::create([
            'nombre'              => 'Boyacá 7 Días',
            'url_sitio'           => 'https://boyaca7dias.com.co',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 82.00,
        ]);

        Documento::create([
            'fuente_id'         => $fuenteEfe->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Tribunal Superior Eleitoral proclama reelección de Lula en Brasil y certifica que Flavio Bolsonaro no ganó en octubre de 2026',
            'fragmento'         => 'El TSE certificó que ni Jair Bolsonaro ni su hijo Flavio Bolsonaro ganaron la presidencia de Brasil en octubre de 2026.',
            'url_articulo'      => 'https://efe.com/elecciones-brasil-2026',
            'fecha_publicacion' => now()->subDays(5),
        ]);

        // Artículo viejo/parcial con "confirma que" que sólo menciona "bolsonaro" y no "flavio"
        Documento::create([
            'fuente_id'         => $fuenteRegional->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'La casa blanca confirma que trump llamo a bolsonaro para felicitarle',
            'fragmento'         => 'La casa blanca confirma que trump llamo a bolsonaro para felicitarle.',
            'url_articulo'      => 'https://boyaca7dias.com.co/bolsonaro-trump-2018',
            'fecha_publicacion' => now()->subDays(10),
        ]);

        $busqueda = $this->servicio->buscarNoticiasPorFuenteYMetadata('Flavio Bolsonaro es presidente de Brasil');
        $hechos   = $this->servicio->verificarHechos(
            'Flavio Bolsonaro es presidente de Brasil',
            ['Flavio Bolsonaro es presidente de Brasil'],
            $busqueda['fuentes_que_responden'],
            $busqueda['noticias_por_fuente']
        );

        $this->assertSame(0.0, $hechos['concordancia_score']);
        $this->assertSame('REFUTA', $hechos['detalles'][$fuenteEfe->id_fuente]['estado_contraste']);
        $this->assertNotSame('CONFIRMA', $hechos['detalles'][$fuenteRegional->id_fuente]['estado_contraste']);

        // Noticia de hace 8 años con "confirma que" no debe confirmar una afirmación actual sin ese año
        $fuenteArchivo = Fuente::create([
            'nombre'              => 'Archivo Histórico Regional',
            'url_sitio'           => 'https://archivo.test',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 85.00,
        ]);

        Documento::create([
            'fuente_id'         => $fuenteArchivo->id_fuente,
            'categoria'         => 'Finanzas y Economía',
            'titulo'            => 'Empresa energética anuncia dividendo extraordinario en bolsa',
            'fragmento'         => 'La junta confirma que repartirá dividendo extraordinario en el ejercicio fiscal de 2018.',
            'url_articulo'      => 'https://archivo.test/dividendo-2018',
            'fecha_publicacion' => now()->subYears(8),
        ]);

        $busquedaAntigua = $this->servicio->buscarNoticiasPorFuenteYMetadata('Empresa energética anuncia dividendo extraordinario en bolsa');
        $hechosAntiguos  = $this->servicio->verificarHechos(
            'Empresa energética anuncia dividendo extraordinario en bolsa',
            ['Empresa energética anuncia dividendo extraordinario en bolsa'],
            $busquedaAntigua['fuentes_que_responden'],
            $busquedaAntigua['noticias_por_fuente']
        );

        $this->assertSame('CONTEXTUALIZA', $hechosAntiguos['detalles'][$fuenteArchivo->id_fuente]['estado_contraste']);
        $this->assertSame(35.0, $hechosAntiguos['concordancia_score']);
    }

    public function test_extraer_palabras_clave_y_busqueda_excluyen_verbos_comunes_html_e_imagenes(): void
    {
        $palabras = $this->servicio->extraerPalabrasClave(
            'Abelardo le hace fracking en el culo a polo polo cada año',
            10
        );

        $this->assertNotContains('hace', $palabras);
        $this->assertNotContains('cada', $palabras);
        $this->assertContains('abelardo', $palabras);
        $this->assertContains('fracking', $palabras);
        $this->assertContains('polo', $palabras);

        $fuenteIrrelevante = Fuente::create([
            'nombre'              => 'Maldita.es',
            'url_sitio'           => 'https://maldita.es',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 95.00,
        ]);

        // 1. Noticia que sólo contiene el verbo "hace" y la palabra "abelardo" dentro de un atributo HTML
        Documento::create([
            'fuente_id'         => $fuenteIrrelevante->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Ejercicio militar de la OTAN que se hace cada año',
            'fragmento'         => '<figure><img src="https://ejemplo.com/abelardo.webp" /></figure> Maniobras en Europa que se hace cada año.',
            'url_articulo'      => 'https://maldita.es/otan-ejercicio',
            'fecha_publicacion' => now()->subDay(),
        ]);

        // 2. Registro basura que solo apunta a un archivo de imagen (.webp)
        Documento::create([
            'fuente_id'         => $fuenteIrrelevante->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Abelardo apt 3 11zon.webp',
            'fragmento'         => 'Abelardo apt 3 11zon.webp. Noticia indexada.',
            'url_articulo'      => 'https://maldita.es/wp-content/uploads/2026/05/Abelardo-apt-3_11zon.webp',
            'fecha_publicacion' => now()->subDay(),
        ]);

        $busquedaSinMatch = $this->servicio->buscarNoticiasPorFuenteYMetadata(
            'Abelardo le hace fracking en el culo a polo polo'
        );

        $this->assertEmpty($busquedaSinMatch['noticias_por_fuente']);

        // 3. Noticia válida con HTML cuyo texto plano sí comparte múltiples términos específicos
        $fuenteValida = Fuente::create([
            'nombre'              => 'Congreso Hoy',
            'url_sitio'           => 'https://congreso.test',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 90.00,
        ]);

        Documento::create([
            'fuente_id'         => $fuenteValida->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Debate sobre fracking y Abelardo',
            'fragmento'         => '<p>Ponencia sobre <strong>fracking</strong> frente a Abelardo en plenaria.</p>',
            'url_articulo'      => 'https://congreso.test/debate-fracking',
            'fecha_publicacion' => now()->subHours(12),
        ]);

        $busquedaValida = $this->servicio->buscarNoticiasPorFuenteYMetadata(
            'Abelardo le hace fracking en el culo a polo polo'
        );

        $this->assertArrayHasKey($fuenteValida->id_fuente, $busquedaValida['noticias_por_fuente']);
        $docRetornado = $busquedaValida['noticias_por_fuente'][$fuenteValida->id_fuente][0];
        $this->assertSame(2, $docRetornado->coincidencias_especificas);
        $this->assertStringNotContainsString('<p>', $docRetornado->fragmento);
        $this->assertStringNotContainsString('<strong>', $docRetornado->fragmento);

        $hechos = $this->servicio->verificarHechos(
            'Abelardo le hace fracking en el culo a polo polo',
            ['Abelardo le hace fracking en el culo a polo polo'],
            $busquedaValida['fuentes_que_responden'],
            $busquedaValida['noticias_por_fuente']
        );

        $this->assertSame(2, $hechos['detalles'][$fuenteValida->id_fuente]['coincidencias_especificas']);
        $this->assertStringNotContainsString('<p>', (string) $hechos['citas'][$fuenteValida->id_fuente]);
    }

    public function test_verificar_hechos_clasifica_como_sin_evidencia_cuando_solo_hay_mencion_aislada_de_un_termino(): void
    {
        $fuenteNacional = Fuente::create([
            'nombre'              => 'El Colombiano Test',
            'url_sitio'           => 'https://elcolombiano.test',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 88.00,
        ]);

        // Noticia que solo menciona "Abelardo" en un contexto de campañas electorales, sin nada del resto de términos
        Documento::create([
            'fuente_id'         => $fuenteNacional->id_fuente,
            'categoria'         => 'Gobierno y Política',
            'titulo'            => 'Así funcionó la campaña política de Abelardo en Colombia',
            'fragmento'         => 'Análisis detallado sobre los discursos del abogado Abelardo en foros públicos y debates de radio.',
            'url_articulo'      => 'https://elcolombiano.test/abelardo-campana',
            'fecha_publicacion' => now()->subDays(3),
        ]);

        $afirmacionInfundada = 'Abelardo le hacen fraking por el culo segun PoloPolo';
        $busqueda = $this->servicio->buscarNoticiasPorFuenteYMetadata($afirmacionInfundada);
        $hechos   = $this->servicio->verificarHechos(
            $afirmacionInfundada,
            [$afirmacionInfundada],
            $busqueda['fuentes_que_responden'],
            $busqueda['noticias_por_fuente']
        );

        // Al haber 4 términos específicos ('abelardo', 'fraking', 'culo', 'polopolo') y coincidir únicamente 1, debe catalogarse como SIN_EVIDENCIA con score 0.0
        $this->assertSame(0.0, $hechos['concordancia_score']);
        $this->assertSame('SIN_EVIDENCIA', $hechos['detalles'][$fuenteNacional->id_fuente]['estado_contraste']);
        $this->assertSame(0.0, $hechos['detalles'][$fuenteNacional->id_fuente]['concordancia']);
    }
}
