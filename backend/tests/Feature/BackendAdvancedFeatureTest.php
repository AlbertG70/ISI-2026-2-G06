<?php

namespace Tests\Feature;

use App\Models\Contenido;
use App\Models\ProcesoVerificacion;
use App\Models\Usuario;
use App\Models\Veredicto;
use App\Services\CircuitBreakerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * BackendAdvancedFeatureTest — Pruebas de Características Avanzadas del Backend
 *
 * Cubre:
 *  - TSK-3.7 & REQ-13: Panel Editorial Humano e Intervención de Veredictos
 *  - TSK-4.3 & REQ-18: Circuit Breaker y Control de Costes LLM
 *  - TSK-4.5: Ley 1581 de 2012 (Hábeas Data y Derecho al Olvido)
 *  - Documentación OpenAPI (/api/docs)
 *
 * @package Tests\Feature
 */
class BackendAdvancedFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $usuarioAdulto;
    private Usuario $usuarioEditor;
    private Usuario $usuarioAdmin;
    private string $tokenAdulto;
    private string $tokenEditor;
    private string $tokenAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuarioAdulto = Usuario::create([
            'email'         => 'adulto.test@dagaz.com',
            'password_hash' => Hash::make('password123'),
            'rol'           => 'adulto',
        ]);

        $this->usuarioEditor = Usuario::create([
            'email'         => 'editor.test@dagaz.com',
            'password_hash' => Hash::make('password123'),
            'rol'           => 'editor',
        ]);

        $this->usuarioAdmin = Usuario::create([
            'email'         => 'admin.test@dagaz.com',
            'password_hash' => Hash::make('password123'),
            'rol'           => 'admin',
        ]);

        $this->tokenAdulto = $this->usuarioAdulto->createToken('test-adulto')->plainTextToken;
        $this->tokenEditor = $this->usuarioEditor->createToken('test-editor')->plainTextToken;
        $this->tokenAdmin  = $this->usuarioAdmin->createToken('test-admin')->plainTextToken;
    }

    // =========================================================================
    // TSK-3.7 & REQ-13: Panel Editorial Humano
    // =========================================================================

    public function test_editor_puede_intervenir_veredicto(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Afirmación dudosa sobre elecciones que requiere corrección editorial.',
            'estado'             => 'VERIFICADO',
        ]);

        $proceso = ProcesoVerificacion::create([
            'id_contenido'        => $contenido->id_contenido,
            'score_confiabilidad' => 45.00,
        ]);

        $veredicto = Veredicto::create([
            'id_contenido'        => $contenido->id_contenido,
            'id_proceso'          => $proceso->id_proceso,
            'tipo_veredicto'      => 'PARCIAL',
            'nivel_confiabilidad' => 45.00,
            'explicacion'         => 'Análisis preliminar de baja confianza.',
            'motivo_breve'        => 'El aviso menciona el día correcto, pero usa otra fecha.',
        ]);

        $response = $this->withToken($this->tokenEditor)
            ->postJson("/api/v1/veredictos/{$veredicto->id_veredicto}/intervenir", [
                'tipo_veredicto'      => 'FALSO',
                'nivel_confiabilidad' => 95.00,
                'explicacion'         => 'Corregido tras contrastar boletín oficial de la Registraduría Nacional.',
                'motivo_intervencion' => 'El análisis automatizado confundió el año del documento referenciado.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('veredicto.tipo_veredicto', 'FALSO')
            ->assertJsonPath('veredicto.motivo_breve', null);

        $this->assertEquals(95.0, (float) $response->json('veredicto.nivel_confiabilidad'));

        $this->assertDatabaseHas('veredictos', [
            'id_veredicto'   => $veredicto->id_veredicto,
            'tipo_veredicto' => 'FALSO',
            'motivo_breve'   => null,
        ]);

        $this->assertDatabaseHas('logs_comunicador_api', [
            'id_proceso'     => $proceso->id_proceso,
            'tipo_operacion' => 'intervencion_editorial',
        ]);
    }

    public function test_usuario_comun_no_puede_intervenir_veredicto(): void
    {
        $contenido = Contenido::create([
            'tipo_entrada'       => 'TEXTO',
            'contenido_original' => 'Afirmación de prueba.',
            'estado'             => 'VERIFICADO',
        ]);

        $proceso = ProcesoVerificacion::create([
            'id_contenido'        => $contenido->id_contenido,
            'score_confiabilidad' => 85.00,
        ]);

        $veredicto = Veredicto::create([
            'id_contenido'        => $contenido->id_contenido,
            'id_proceso'          => $proceso->id_proceso,
            'tipo_veredicto'      => 'VERDADERO',
            'nivel_confiabilidad' => 85.00,
            'explicacion'         => 'Explicación original.',
        ]);

        $response = $this->withToken($this->tokenAdulto)
            ->postJson("/api/v1/veredictos/{$veredicto->id_veredicto}/intervenir", [
                'tipo_veredicto'      => 'FALSO',
                'nivel_confiabilidad' => 90.00,
                'explicacion'         => 'Intento de edición no autorizada por usuario común.',
                'motivo_intervencion' => 'No tengo permisos.',
            ]);

        $response->assertStatus(403);
    }

    // =========================================================================
    // TSK-4.3 & REQ-18: Circuit Breaker
    // =========================================================================

    public function test_admin_puede_consultar_metricas_circuit_breaker(): void
    {
        $response = $this->withToken($this->tokenAdmin)
            ->getJson('/api/v1/admin/circuit-breaker');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'metricas' => [
                    'circuito_activo',
                    'estado',
                    'tokens_hora_actual',
                    'tokens_max_hora',
                    'porcentaje_hora',
                    'gasto_hoy_usd',
                    'presupuesto_hoy_usd',
                ],
            ]);
    }

    public function test_usuario_comun_no_puede_acceder_a_circuit_breaker(): void
    {
        $this->withToken($this->tokenAdulto)
            ->getJson('/api/v1/admin/circuit-breaker')
            ->assertStatus(403);
    }

    public function test_admin_puede_reiniciar_circuit_breaker(): void
    {
        $response = $this->withToken($this->tokenAdmin)
            ->postJson('/api/v1/admin/circuit-breaker/reset');

        $response->assertStatus(200)
            ->assertJsonPath('mensaje', 'Circuit Breaker restablecido exitosamente.');
    }

    public function test_circuit_breaker_service_detecta_limite(): void
    {
        /** @var CircuitBreakerService $circuitBreaker */
        $circuitBreaker = app(CircuitBreakerService::class);
        $circuitBreaker->reiniciar();

        $this->assertTrue($circuitBreaker->puedeEjecutar(100));

        // Forzar apertura del circuito
        $circuitBreaker->abrirCircuito('Prueba unitaria de sobrecoste simulada.');

        $this->assertTrue($circuitBreaker->estaAbierto());
        $this->assertFalse($circuitBreaker->puedeEjecutar(100));

        // Limpiar para siguientes tests
        $circuitBreaker->reiniciar();
    }

    // =========================================================================
    // TSK-4.5: Ley 1581 de 2012 (Hábeas Data y Supresión de Cuenta)
    // =========================================================================

    public function test_usuario_puede_descargar_sus_datos_personales(): void
    {
        $response = $this->withToken($this->tokenAdulto)
            ->getJson('/api/v1/auth/datos-personales');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'aviso_legal',
                'fecha_exportacion',
                'titular' => ['id_usuario', 'email', 'rol'],
                'actividad' => ['total_contenidos', 'total_notas', 'total_historial'],
            ])
            ->assertJsonPath('titular.email', 'adulto.test@dagaz.com');
    }

    public function test_usuario_puede_eliminar_su_cuenta_con_password(): void
    {
        $usuarioBorrar = Usuario::create([
            'email'         => 'para.eliminar@dagaz.com',
            'password_hash' => Hash::make('ClaveSegura123'),
            'rol'           => 'adulto',
        ]);

        $tokenBorrar = $usuarioBorrar->createToken('test')->plainTextToken;

        // Falla con contraseña incorrecta
        $this->withToken($tokenBorrar)
            ->deleteJson('/api/v1/auth/cuenta', [
                'password' => 'ContraseñaEquivocada',
            ])
            ->assertStatus(422);

        // Pasa con contraseña correcta
        $response = $this->withToken($tokenBorrar)
            ->deleteJson('/api/v1/auth/cuenta', [
                'password' => 'ClaveSegura123',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['mensaje']);

        $this->assertDatabaseMissing('usuarios', [
            'id_usuario' => $usuarioBorrar->id_usuario,
        ]);
    }

    // =========================================================================
    // Contrato OpenAPI 3.1 (/api/docs)
    // =========================================================================

    public function test_endpoint_docs_retorna_especificacion_openapi(): void
    {
        $response = $this->get('/api/docs');

        $response->assertStatus(200);
        $this->assertStringContainsString('openapi: 3.1.0', $response->getContent());
        $this->assertStringContainsString('DAGAZ API', $response->getContent());
    }
}
