<?php

namespace Tests\Feature;

use App\Models\Contenido;
use App\Models\Fuente;
use App\Models\Historial;
use App\Models\Nota;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * VerificacionFeatureTest — Pruebas de Integración de los Endpoints Principales
 *
 * Cubre los casos de uso CU01 a CU07 a nivel de API HTTP.
 * Usa RefreshDatabase para garantizar aislamiento entre tests.
 *
 * Valores alineados con el esquema real de la BD raiden (MariaDB 10.4.32):
 *   - tipo_entrada: 'TEXTO','URL','IMAGEN_OCR','RED_SOCIAL'
 *   - estado:       'PENDIENTE','PROCESANDO','VERIFICADO','ERROR'
 *   - tipo_veredicto: 'VERDADERO','FALSO','ENGAÑOSO','PARCIAL','NO_VERIFICABLE'
 *   - tipo_fuente:  'OFICIAL','PERIODISTICO','ACADEMICO','RED_SOCIAL','OTRO'
 *   - calificacion: 0.0 a 10.0
 *
 * @package Tests\Feature
 */
class VerificacionFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuarioAdulto;
    private Usuario $usuarioAdmin;
    private string $tokenAdulto;
    private string $tokenAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear usuarios de prueba
        $this->usuarioAdulto = Usuario::create([
            'email'         => 'adulto@dagaz.com',
            'password_hash' => Hash::make('adulto1234'),
            'rol'           => 'adulto',
        ]);

        $this->usuarioAdmin = Usuario::create([
            'email'         => 'admin@dagaz.com',
            'password_hash' => Hash::make('admin1234'),
            'rol'           => 'admin',
        ]);

        $this->tokenAdulto = $this->usuarioAdulto->createToken('test')->plainTextToken;
        $this->tokenAdmin  = $this->usuarioAdmin->createToken('test-admin')->plainTextToken;
    }

    // =========================================================================
    // CU04 — Historial (sin contenidos)
    // =========================================================================

    public function test_historial_vacio_retorna_mensaje_amigable(): void
    {
        $this->withToken($this->tokenAdulto)
            ->getJson('/api/v1/historial')
            ->assertStatus(200)
            ->assertJsonPath('total', 0)
            ->assertJsonStructure(['mensaje', 'historial', 'total']);
    }

    public function test_historial_lista_contenidos_del_usuario(): void
    {
        // Crear contenido y registrar en historial
        $contenido = Contenido::create([
            'id_usuario'         => $this->usuarioAdulto->id_usuario,
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Noticias falsas sobre las elecciones en Colombia.',
            'estado'             => 'VERIFICADO',
        ]);

        Historial::create([
            'id_usuario'   => $this->usuarioAdulto->id_usuario,
            'id_contenido' => $contenido->id_contenido,
            'accion'       => 'verificado',
        ]);

        $response = $this->withToken($this->tokenAdulto)
            ->getJson('/api/v1/historial');

        $response->assertStatus(200)
            ->assertJsonPath('total', 1);
    }

    // =========================================================================
    // CU03 — Guardar Contenidos
    // =========================================================================

    public function test_guardar_contenido_crea_registro_en_historial(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Afirmación a guardar en historial.',
            'estado'             => 'VERIFICADO',
        ]);

        $this->withToken($this->tokenAdulto)
            ->postJson("/api/v1/historial/{$contenido->id_contenido}")
            ->assertStatus(200)
            ->assertJsonPath('mensaje', 'Noticia guardada exitosamente en su historial.');

        $this->assertDatabaseHas('historial', [
            'id_usuario'   => $this->usuarioAdulto->id_usuario,
            'id_contenido' => $contenido->id_contenido,
            'accion'       => 'guardado',
        ]);
    }

    public function test_guardar_contenido_no_duplica_entrada_en_historial(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Contenido a guardar dos veces.',
            'estado'             => 'VERIFICADO',
        ]);

        // Guardar dos veces
        $this->withToken($this->tokenAdulto)->postJson("/api/v1/historial/{$contenido->id_contenido}");
        $this->withToken($this->tokenAdulto)->postJson("/api/v1/historial/{$contenido->id_contenido}");

        // Solo debe haber un registro en el historial para esa acción
        $this->assertDatabaseCount('historial', 1);
    }

    public function test_guardar_contenido_inexistente_retorna_404(): void
    {
        $uuidInexistente = '00000000-0000-0000-0000-000000000000';

        $this->withToken($this->tokenAdulto)
            ->postJson("/api/v1/historial/{$uuidInexistente}")
            ->assertStatus(404);
    }

    // =========================================================================
    // CU01 — Notas Complementarias
    // =========================================================================

    public function test_agregar_nota_a_contenido_verificado(): void
    {
        $contenido = Contenido::create([
            'id_usuario'         => $this->usuarioAdulto->id_usuario,
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Texto original del contenido para nota.',
            'estado'             => 'VERIFICADO',
        ]);

        $proceso = ProcesoVerificacion::create([
            'id_contenido'        => $contenido->id_contenido,
            'score_confiabilidad' => 80.00,
        ]);

        Veredicto::create([
            'id_contenido'        => $contenido->id_contenido,
            'id_proceso'          => $proceso->id_proceso,
            'tipo_veredicto'      => 'VERDADERO',
            'nivel_confiabilidad' => 80.00,
            'explicacion'         => 'Contenido verificado como verdadero.',
        ]);

        $response = $this->withToken($this->tokenAdulto)
            ->postJson("/api/v1/contenidos/{$contenido->id_contenido}/notas", [
                'contenido_nota' => 'Esta nota proporciona contexto adicional sobre este importante hallazgo periodístico verificado.',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'mensaje',
                'nota' => ['id_nota', 'contenido_nota', 'calificacion', 'fecha_creacion'],
            ]);

        $this->assertEquals(0.0, (float) $response->json('nota.calificacion'));

        $this->assertDatabaseHas('notas', [
            'id_usuario'   => $this->usuarioAdulto->id_usuario,
            'id_contenido' => $contenido->id_contenido,
        ]);
    }

    public function test_nota_vacia_es_rechazada(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Contenido de prueba para nota vacía.',
            'estado'             => 'VERIFICADO',
        ]);

        $this->withToken($this->tokenAdulto)
            ->postJson("/api/v1/contenidos/{$contenido->id_contenido}/notas", [
                'contenido_nota' => '   ', // Solo espacios
            ])
            ->assertStatus(422);
    }

    public function test_nota_en_contenido_sin_veredicto_es_rechazada(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Contenido pendiente de verificación sin veredicto.',
            'estado'             => 'PENDIENTE',
        ]);

        $this->withToken($this->tokenAdulto)
            ->postJson("/api/v1/contenidos/{$contenido->id_contenido}/notas", [
                'contenido_nota' => 'Esta nota tiene contexto suficiente y extenso para superar el mínimo.',
            ])
            ->assertStatus(422);
    }

    // =========================================================================
    // CU02 — Calificar Notas
    // =========================================================================

    public function test_calificar_nota_actualiza_calificacion(): void
    {
        $otroUsuario = Usuario::create([
            'email'         => 'otro@dagaz.com',
            'password_hash' => Hash::make('otro1234'),
            'rol'           => 'adulto',
        ]);

        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Texto de prueba para calificación.',
            'estado'             => 'VERIFICADO',
        ]);

        // La nota pertenece a otroUsuario
        $nota = Nota::create([
            'id_usuario'     => $otroUsuario->id_usuario,
            'id_contenido'   => $contenido->id_contenido,
            'contenido_nota' => 'Nota comunitaria de contexto sobre este importante tema.',
            'calificacion'   => 0.0,
        ]);

        // El adulto original califica la nota del otro usuario (rango 0-10)
        $this->withToken($this->tokenAdulto)
            ->patchJson("/api/v1/notas/{$nota->id_nota}/calificar", [
                'calificacion' => 7.0,
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['mensaje', 'calificacion_actual']);
    }

    public function test_usuario_no_puede_calificar_su_propia_nota(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Texto para prueba de autokalificación.',
            'estado'             => 'VERIFICADO',
        ]);

        $nota = Nota::create([
            'id_usuario'     => $this->usuarioAdulto->id_usuario,
            'id_contenido'   => $contenido->id_contenido,
            'contenido_nota' => 'Mi propia nota extensa sobre este contenido verificado.',
            'calificacion'   => 0.0,
        ]);

        $this->withToken($this->tokenAdulto)
            ->patchJson("/api/v1/notas/{$nota->id_nota}/calificar", [
                'calificacion' => 8.0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('mensaje', 'No puede calificar su propia nota.');
    }

    // =========================================================================
    // CU07 — Fuentes del Grafo de Credibilidad
    // =========================================================================

    public function test_listar_fuentes_retorna_grafo_de_credibilidad(): void
    {
        // Crear fuentes de prueba con tipos reales de la BD raiden
        Fuente::create([
            'nombre'              => 'Reuters',
            'url_sitio'           => 'https://reuters.com',
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 98.50,
        ]);

        $response = $this->withToken($this->tokenAdulto)
            ->getJson('/api/v1/fuentes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'fuentes' => [['id_fuente', 'nombre', 'url_sitio', 'tipo_fuente', 'indice_credibilidad']],
                'total',
            ]);
    }

    public function test_valorar_fuente_actualiza_indice_credibilidad(): void
    {
        $fuente = Fuente::create([
            'nombre'              => 'ColombiaCheck',
            'url_sitio'           => 'https://colombiacheck.com',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 80.00,
        ]);

        $this->withToken($this->tokenAdulto)
            ->patchJson("/api/v1/fuentes/{$fuente->id_fuente}/valorar", [
                'calificacion' => 90.0,
            ])
            ->assertStatus(200)
            ->assertJsonStructure(['mensaje', 'indice_credibilidad']);
    }

    public function test_usuario_adulto_no_puede_reclasificar_fuente(): void
    {
        $fuente = Fuente::create([
            'nombre'              => 'Fuente Test Adulto',
            'url_sitio'           => 'https://example.com',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 60.00,
        ]);

        $this->withToken($this->tokenAdulto)
            ->patchJson("/api/v1/fuentes/{$fuente->id_fuente}/clasificar", [
                'tipo_fuente' => 'OFICIAL',
            ])
            ->assertStatus(403);
    }

    public function test_admin_puede_reclasificar_fuente(): void
    {
        $fuente = Fuente::create([
            'nombre'              => 'Fuente Test Admin',
            'url_sitio'           => 'https://example.com',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 60.00,
        ]);

        $this->withToken($this->tokenAdmin)
            ->patchJson("/api/v1/fuentes/{$fuente->id_fuente}/clasificar", [
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 95.00,
            ])
            ->assertStatus(200);
    }

    public function test_usuario_adulto_no_puede_ver_logs_de_api(): void
    {
        $this->withToken($this->tokenAdulto)
            ->getJson('/api/v1/admin/logs-api')
            ->assertStatus(403);
    }

    public function test_admin_puede_ver_logs_de_api(): void
    {
        $this->withToken($this->tokenAdmin)
            ->getJson('/api/v1/admin/logs-api')
            ->assertStatus(200);
    }

    // =========================================================================
    // Health Check
    // =========================================================================

    public function test_health_check_retorna_estado_operativo(): void
    {
        $this->getJson('/api/health')
            ->assertStatus(200)
            ->assertJsonPath('estado', 'operativo')
            ->assertJsonPath('sistema', 'DAGAZ');
    }

    // =========================================================================
    // TSK-6.7: Streaming Reactivo SSE (Híbrido A + B)
    // =========================================================================

    public function test_verificar_contenido_stream_emite_eventos_sse_hibridos(): void
    {
        $response = $this->post('/api/v1/verificacion/stream', [
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'El Ministerio de Salud de Colombia inició campaña de vacunación preventiva.',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('event: inicio', $content);
        $this->assertStringContainsString('event: paso', $content);
        $this->assertStringContainsString('event: fuentes', $content);
        $this->assertStringContainsString('event: final', $content);
        $this->assertStringContainsString('event: fin', $content);
        $this->assertStringContainsString('[DONE]', $content);
    }
}
