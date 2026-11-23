<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Contenido;
use App\Models\Fuente;
use App\Models\LogComunicadorApi;
use App\Models\Nota;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use App\Services\AuditoriaExportService;
use Database\Seeders\FuentesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * AuditoriaExportTest — Pruebas Automatizadas de Auditoría para Reguladores y Medios
 *
 * TSK-4.6 (Sprint 4 / REQ-25): Exportador estructurado de auditoría en JSON forense,
 * ClaimReview JSON-LD (Schema.org / IFCN) y CSV tabular (RFC 4180).
 *
 * @package Tests\Feature
 */
class AuditoriaExportTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuarioAdmin;
    private Usuario $usuarioEditor;
    private Usuario $usuarioAdulto;
    private string $tokenAdmin;
    private string $tokenEditor;
    private string $tokenAdulto;
    private string $apiKeyPartner = 'dagaz_partner_test_key_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuarioAdmin = Usuario::create([
            'email'         => 'admin.auditor@dagaz.gov.co',
            'password_hash' => Hash::make('secret1234'),
            'rol'           => 'admin',
        ]);

        $this->usuarioEditor = Usuario::create([
            'email'         => 'editor.auditor@dagaz.com',
            'password_hash' => Hash::make('secret1234'),
            'rol'           => 'editor',
        ]);

        $this->usuarioAdulto = Usuario::create([
            'email'         => 'ciudadano@dagaz.com',
            'password_hash' => Hash::make('secret1234'),
            'rol'           => 'adulto',
        ]);

        $this->tokenAdmin  = $this->usuarioAdmin->createToken('admin-token')->plainTextToken;
        $this->tokenEditor = $this->usuarioEditor->createToken('editor-token')->plainTextToken;
        $this->tokenAdulto = $this->usuarioAdulto->createToken('adulto-token')->plainTextToken;

        Config::set('dagaz.partners.keys', [
            $this->apiKeyPartner => [
                'id_socio'          => 'socio-test',
                'nombre'            => 'Redacción de Prueba Piloto',
                'email_contacto'    => 'piloto@dagaz.test',
                'rate_limit_minute' => 60,
                'activo'            => true,
                'permisos'          => ['verificar', 'consultar', 'fuentes', 'auditoria'],
            ],
        ]);
    }

    private function crearContenidoVerificadoCompleto(
        string $tipoVeredicto = 'VERDADERO',
        float $score = 92.50,
        string $afirmacion = 'El DANE publica las cifras oficiales de empleo en Colombia.'
    ): array {
        $contenido = Contenido::create([
            'id_usuario'         => $this->usuarioAdulto->id_usuario,
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => $afirmacion,
            'url_origen'         => 'https://dane.gov.co/boletin-empleo',
            'estado'             => 'VERIFICADO',
            'fecha_subida'       => Carbon::now()->subDays(2),
        ]);

        $proceso = ProcesoVerificacion::create([
            'id_contenido'        => $contenido->id_contenido,
            'score_confiabilidad' => $score,
            'analisis_lenguaje'   => ['tono' => 'informativo', 'subjetividad' => 0.05],
            'contraste_temas'     => ['temas' => ['economía', 'empleo', 'dane']],
            'fecha_analisis'      => Carbon::now()->subDays(2),
        ]);

        $veredicto = Veredicto::create([
            'id_contenido'        => $contenido->id_contenido,
            'id_proceso'          => $proceso->id_proceso,
            'tipo_veredicto'      => $tipoVeredicto,
            'nivel_confiabilidad' => $score,
            'explicacion'         => 'Cifras contrastadas directamente con el boletín del DANE y Banco de la República.',
            'fecha_emision'       => Carbon::now()->subDays(2),
        ]);

        $fuente = Fuente::create([
            'nombre'              => 'DANE - Departamento Administrativo Nacional de Estadística',
            'url_sitio'           => 'https://dane.gov.co',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 99.00,
        ]);

        $veredicto->fuentes()->attach($fuente->id_fuente, [
            'peso_en_veredicto' => 0.95,
            'cita_contraste'    => 'Boletín técnico oficial del mercado laboral.',
        ]);

        Nota::create([
            'id_usuario'     => $this->usuarioAdulto->id_usuario,
            'id_contenido'   => $contenido->id_contenido,
            'contenido_nota' => 'Nota ciudadana confirmando la consulta del informe oficial.',
            'calificacion'   => 8.5,
        ]);

        LogComunicadorApi::create([
            'id_proceso'       => $proceso->id_proceso,
            'servicio'         => 'OpenAI',
            'tipo_operacion'   => 'descomposicion',
            'codigo_respuesta' => 200,
        ]);

        return [
            'contenido' => $contenido,
            'proceso'   => $proceso,
            'veredicto' => $veredicto,
            'fuente'    => $fuente,
        ];
    }

    public function test_editor_puede_exportar_expediente_en_formato_json(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withToken($this->tokenEditor)
            ->getJson("/api/v1/auditoria/veredictos/{$idContenido}?formato=json");

        $response->assertStatus(200)
            ->assertJsonPath('metadatos_auditoria.esquema', 'v1.0-auditable')
            ->assertJsonPath('contenido.id_contenido', $idContenido)
            ->assertJsonPath('veredicto.tipo_veredicto', 'VERDADERO')
            ->assertJsonPath('fuentes_evidencia.total_fuentes', 1)
            ->assertJsonStructure([
                'metadatos_auditoria' => [
                    'esquema',
                    'sistema',
                    'version_sistema',
                    'marco_normativo',
                    'fecha_emision_reporte',
                    'sha256_integridad',
                ],
                'contenido' => [
                    'id_contenido',
                    'tipo_entrada',
                    'contenido_original',
                    'hash_contenido',
                    'estado',
                ],
                'proceso_verificacion' => [
                    'id_proceso',
                    'score_confiabilidad',
                    'total_llamadas_api',
                ],
                'veredicto' => [
                    'id_veredicto',
                    'tipo_veredicto',
                    'nivel_confiabilidad',
                    'explicacion',
                    'fecha_emision',
                ],
                'fuentes_evidencia' => [
                    'total_fuentes',
                    'detalle',
                ],
                'intervenciones_editoriales',
                'notas_comunitarias',
                'trazabilidad_tecnica',
            ]);

        $sha = $response->json('metadatos_auditoria.sha256_integridad');
        $this->assertNotEmpty($sha);
        $this->assertEquals(64, strlen($sha)); // 64 caracteres hexadecimales SHA-256
    }

    public function test_editor_puede_exportar_expediente_en_formato_claim_review_json_ld(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto('FALSO', 95.0, 'El gobierno decreta feriado bancario indefinido.');
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withToken($this->tokenEditor)
            ->get("/api/v1/auditoria/veredictos/{$idContenido}?formato=json-ld");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/ld+json; charset=UTF-8')
            ->assertJsonPath('@context', 'https://schema.org')
            ->assertJsonPath('@type', 'ClaimReview')
            ->assertJsonPath('author.name', 'DAGAZ Verificación Colombia')
            ->assertJsonPath('reviewRating.alternateName', 'FALSO')
            ->assertJsonPath('reviewRating.ratingValue', 1)
            ->assertJsonPath('itemReviewed.@type', 'Claim');
    }

    public function test_endpoint_directo_claim_review_retorna_json_ld(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto('ENGAÑOSO');
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withToken($this->tokenAdmin)
            ->get("/api/v1/auditoria/claim-review/{$idContenido}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/ld+json; charset=UTF-8')
            ->assertJsonPath('@type', 'ClaimReview')
            ->assertJsonPath('reviewRating.alternateName', 'ENGAÑOSO')
            ->assertJsonPath('reviewRating.ratingValue', 2);
    }

    public function test_editor_puede_exportar_expediente_en_formato_csv(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withToken($this->tokenEditor)
            ->get("/api/v1/auditoria/veredictos/{$idContenido}?formato=csv");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();

        // Validar BOM UTF-8
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        // Validar encabezados tabulares RFC 4180
        $this->assertStringContainsString('ID_CONTENIDO', $content);
        $this->assertStringContainsString('TIPO_VEREDICTO', $content);
        $this->assertStringContainsString('CHECKSUM_SHA256', $content);
        $this->assertStringContainsString($idContenido, $content);
        $this->assertStringContainsString('VERDADERO', $content);
    }

    public function test_integridad_sha256_detecta_modificaciones_y_es_consistente(): void
    {
        $service = app(AuditoriaExportService::class);

        $payloadA = [
            'id_contenido'   => 'abc-1',
            'tipo_veredicto' => 'VERDADERO',
            'score'          => 90.0,
        ];

        $payloadB = [
            'id_contenido'   => 'abc-1',
            'tipo_veredicto' => 'FALSO', // alterado
            'score'          => 90.0,
        ];

        $hashA = $service->calcularChecksum($payloadA);
        $hashB = $service->calcularChecksum($payloadB);

        $this->assertNotEquals($hashA, $hashB);
        $this->assertEquals($hashA, $service->calcularChecksum($payloadA)); // Determinista
    }

    public function test_expediente_incluye_intervenciones_editoriales_humanas(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();
        $proceso = $fixture['proceso'];

        // Registrar una intervención editorial humana
        LogComunicadorApi::create([
            'id_proceso'       => $proceso->id_proceso,
            'servicio'         => 'Panel Editorial Humano',
            'tipo_operacion'   => 'intervencion_editorial',
            'codigo_respuesta' => 200,
            'detalle_error'    => json_encode([
                'editor' => 'editor.auditor@dagaz.com',
                'motivo' => 'Revisión por nueva evidencia del DANE.',
            ]),
        ]);

        $response = $this->withToken($this->tokenAdmin)
            ->getJson("/api/v1/auditoria/veredictos/{$fixture['contenido']->id_contenido}");

        $response->assertStatus(200)
            ->assertJsonPath('veredicto.tiene_intervencion_editorial', true);

        $intervenciones = $response->json('intervenciones_editoriales');
        $this->assertNotEmpty($intervenciones);
        $this->assertEquals('editor.auditor@dagaz.com', $intervenciones[0]['detalle']['editor']);
    }

    public function test_exportar_lote_masivo_en_json_con_filtros(): void
    {
        $this->crearContenidoVerificadoCompleto('VERDADERO', 90.0, 'Noticia A sobre salud.');
        $this->crearContenidoVerificadoCompleto('FALSO', 95.0, 'Noticia B sobre finanzas.');

        $hoy = Carbon::now()->format('Y-m-d');
        $pasado = Carbon::now()->subDays(5)->format('Y-m-d');

        $response = $this->withToken($this->tokenAdmin)
            ->getJson("/api/v1/auditoria/expedientes?desde={$pasado}&hasta={$hoy}&tipo_veredicto=VERDADERO");

        $response->assertStatus(200)
            ->assertJsonPath('resumen_auditoria.filtros_aplicados.tipo_veredicto', 'VERDADERO')
            ->assertJsonStructure([
                'resumen_auditoria' => [
                    'total_expedientes',
                    'distribucion_veredictos',
                    'filtros_aplicados',
                    'fecha_generacion',
                ],
                'sha256_lote',
                'expedientes',
            ]);

        $expedientes = $response->json('expedientes');
        $this->assertNotEmpty($expedientes);
        foreach ($expedientes as $exp) {
            $this->assertEquals('VERDADERO', $exp['veredicto']['tipo_veredicto']);
        }
    }

    public function test_exportar_lote_masivo_en_formato_csv(): void
    {
        $this->crearContenidoVerificadoCompleto('VERDADERO');
        $this->crearContenidoVerificadoCompleto('FALSO');

        $response = $this->withToken($this->tokenEditor)
            ->get("/api/v1/auditoria/expedientes?formato=csv");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('ID_CONTENIDO', $content);
        $this->assertStringContainsString('TOTAL_FUENTES', $content);
    }

    public function test_usuario_adulto_recibe_403_prohibido(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();

        $response = $this->withToken($this->tokenAdulto)
            ->getJson("/api/v1/auditoria/veredictos/{$fixture['contenido']->id_contenido}");

        $response->assertStatus(403);
    }

    public function test_solicitud_sin_autenticacion_recibe_401(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();

        $response = $this->getJson("/api/v1/auditoria/veredictos/{$fixture['contenido']->id_contenido}");

        $response->assertStatus(401);
    }

    public function test_redaccion_socia_puede_exportar_auditoria_mediante_api_key(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withHeader('X-API-KEY', $this->apiKeyPartner)
            ->getJson("/api/v1/public/auditoria/{$idContenido}?formato=json");

        $response->assertStatus(200)
            ->assertHeader('X-Partner-Id', 'socio-test')
            ->assertJsonPath('contenido.id_contenido', $idContenido)
            ->assertJsonPath('metadatos_auditoria.esquema', 'v1.0-auditable');
    }

    public function test_redaccion_socia_puede_obtener_claim_review_con_api_key(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withHeader('X-API-KEY', $this->apiKeyPartner)
            ->get("/api/v1/public/claim-review/{$idContenido}");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/ld+json; charset=UTF-8')
            ->assertJsonPath('@type', 'ClaimReview');
    }

    public function test_contenido_inexistente_retorna_404(): void
    {
        $uuidFalso = (string) Str::uuid();

        $response = $this->withToken($this->tokenAdmin)
            ->getJson("/api/v1/auditoria/veredictos/{$uuidFalso}");

        $response->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    public function test_formato_no_soportado_retorna_422(): void
    {
        $fixture = $this->crearContenidoVerificadoCompleto();
        $idContenido = $fixture['contenido']->id_contenido;

        $response = $this->withToken($this->tokenEditor)
            ->getJson("/api/v1/auditoria/veredictos/{$idContenido}?formato=xml");

        $response->assertStatus(422)
            ->assertJsonPath('error', 'formato_no_soportado')
            ->assertJsonStructure(['error', 'mensaje', 'formatos_validos']);
    }
}
