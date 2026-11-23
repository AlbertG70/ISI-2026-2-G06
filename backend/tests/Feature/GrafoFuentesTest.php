<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Fuente;
use App\Models\Usuario;
use Database\Seeders\FuentesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GrafoFuentesTest — Suite de Pruebas del Grafo de Credibilidad de DAGAZ
 *
 * TSK-3.1 (Sprint 3 / REQ-14): Ampliación a 40 fuentes (Regional y Nacional Colombia).
 * TSK-4.1 (Sprint 4 / REQ-20): Ampliación a 80 fuentes (Verificadores certificados IFCN).
 * CU07: Asignar Confiabilidad a las Fuentes (Valoración por usuario y reclasificación por admin).
 */
class GrafoFuentesTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $adulto;
    private Usuario $admin;
    private string $tokenAdulto;
    private string $tokenAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adulto = Usuario::create([
            'email'         => 'adulto.fuentes@dagaz.com',
            'password_hash' => bcrypt('Adulto123!'),
            'rol'           => 'adulto',
        ]);

        $this->admin = Usuario::create([
            'email'         => 'admin.fuentes@dagaz.com',
            'password_hash' => bcrypt('Admin123!'),
            'rol'           => 'admin',
        ]);

        $this->tokenAdulto = $this->adulto->createToken('token-adulto')->plainTextToken;
        $this->tokenAdmin  = $this->admin->createToken('token-admin')->plainTextToken;

        // Ejecutar el seeder completo del grafo
        $this->seed(FuentesSeeder::class);
    }

    public function test_seeder_puebla_grafo_completo_con_al_menos_80_fuentes(): void
    {
        $total = Fuente::count();
        $this->assertGreaterThanOrEqual(80, $total);
    }

    public function test_distribucion_de_niveles_del_grafo(): void
    {
        $fuentes = Fuente::all();

        $nivel1 = $fuentes->filter(fn ($f) => $f->getNivel() === 1);
        $nivel2 = $fuentes->filter(fn ($f) => $f->getNivel() === 2);
        $nivel3 = $fuentes->filter(fn ($f) => $f->getNivel() === 3);
        $nivel4 = $fuentes->filter(fn ($f) => $f->getNivel() === 4);

        // Cada nivel debe tener representación sustancial en el grafo
        $this->assertGreaterThanOrEqual(15, $nivel1->count(), 'Nivel 1 (Internacional) debe tener al menos 15 fuentes');
        $this->assertGreaterThanOrEqual(15, $nivel2->count(), 'Nivel 2 (Regional Colombia) debe tener al menos 15 fuentes');
        $this->assertGreaterThanOrEqual(15, $nivel3->count(), 'Nivel 3 (Nacional Colombia) debe tener al menos 15 fuentes');
        $this->assertGreaterThanOrEqual(15, $nivel4->count(), 'Nivel 4 (IFCN/Control) debe tener al menos 15 fuentes');

        // Validar etiquetas legibles
        $primeraN1 = $nivel1->first();
        $this->assertNotNull($primeraN1);
        $this->assertStringContainsString('Nivel 1', $primeraN1->getNivelEtiqueta());

        $primeraN4 = $nivel4->first();
        $this->assertNotNull($primeraN4);
        $this->assertStringContainsString('Nivel 4', $primeraN4->getNivelEtiqueta());
    }

    public function test_indices_de_credibilidad_estan_calibrados_entre_70_y_100(): void
    {
        $fuentes = Fuente::all();

        foreach ($fuentes as $fuente) {
            $cred = (float) $fuente->indice_credibilidad;
            $this->assertGreaterThanOrEqual(70.0, $cred, "Fuente {$fuente->nombre} con credibilidad demasiado baja");
            $this->assertLessThanOrEqual(100.0, $cred, "Fuente {$fuente->nombre} supera el 100%");
        }
    }

    public function test_endpoint_listar_fuentes_retorna_grafo_con_clasificacion_y_niveles(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdulto)
            ->getJson('/api/v1/fuentes');

        $response->assertStatus(200)
            ->assertJsonStructure([
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
                'total',
                'rol_usuario',
            ]);

        $this->assertGreaterThanOrEqual(80, $response->json('total'));
    }

    public function test_endpoint_filtrar_fuentes_por_nivel(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdulto)
            ->getJson('/api/v1/fuentes?nivel=4');

        $response->assertStatus(200);
        $fuentes = $response->json('fuentes');

        $this->assertNotEmpty($fuentes);
        foreach ($fuentes as $f) {
            $this->assertSame(4, $f['nivel'], "La fuente {$f['nombre']} no es de Nivel 4");
        }
    }

    public function test_endpoint_filtrar_fuentes_por_tipo(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdulto)
            ->getJson('/api/v1/fuentes?tipo_fuente=OFICIAL');

        $response->assertStatus(200);
        $fuentes = $response->json('fuentes');

        $this->assertNotEmpty($fuentes);
        foreach ($fuentes as $f) {
            $this->assertSame('OFICIAL', $f['tipo_fuente']);
        }
    }

    public function test_endpoint_buscar_fuente_por_nombre(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdulto)
            ->getJson('/api/v1/fuentes?buscar=ColombiaCheck');

        $response->assertStatus(200);
        $fuentes = $response->json('fuentes');

        $this->assertNotEmpty($fuentes);
        $this->assertStringContainsString('ColombiaCheck', $fuentes[0]['nombre']);
    }

    public function test_endpoint_valorar_fuente_por_usuario_adulto(): void
    {
        $fuente = Fuente::where('nombre', 'ColombiaCheck')->firstOrFail();
        $indiceAnterior = (float) $fuente->indice_credibilidad;

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdulto)
            ->patchJson("/api/v1/fuentes/{$fuente->id_fuente}/valorar", [
                'calificacion' => 100.0,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['mensaje', 'indice_credibilidad']);

        $fuenteActualizada = Fuente::find($fuente->id_fuente);
        $this->assertNotNull($fuenteActualizada);
        // Debe haberse movido ligeramente hacia 100 por el factor de suavizado alpha=0.1
        $this->assertGreaterThanOrEqual($indiceAnterior, (float) $fuenteActualizada->indice_credibilidad);
    }

    public function test_usuario_adulto_no_puede_reclasificar_fuente(): void
    {
        $fuente = Fuente::firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdulto)
            ->patchJson("/api/v1/fuentes/{$fuente->id_fuente}/clasificar", [
                'tipo_fuente' => 'OFICIAL',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_puede_reclasificar_fuente(): void
    {
        $fuente = Fuente::firstOrFail();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->patchJson("/api/v1/fuentes/{$fuente->id_fuente}/clasificar", [
                'tipo_fuente'         => 'OFICIAL',
                'indice_credibilidad' => 99.00,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('fuente.tipo_fuente', 'OFICIAL');

        $this->assertEquals(99.0, (float) $response->json('fuente.indice_credibilidad'));

        $this->assertDatabaseHas('fuentes', [
            'id_fuente'           => $fuente->id_fuente,
            'tipo_fuente'         => 'OFICIAL',
            'indice_credibilidad' => 99.00,
        ]);
    }

    public function test_admin_puede_crear_nueva_fuente(): void
    {
        $nuevaFuente = [
            'nombre'              => 'Fact-Checking Test Extra Lab',
            'url_sitio'           => 'https://extra-lab.org',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 92.50,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->tokenAdmin)
            ->postJson('/api/v1/fuentes', $nuevaFuente);

        $response->assertStatus(201)
            ->assertJsonPath('fuente.nombre', 'Fact-Checking Test Extra Lab');

        $this->assertDatabaseHas('fuentes', [
            'nombre' => 'Fact-Checking Test Extra Lab',
        ]);
    }
}
