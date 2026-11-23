<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * FrontendRoutesTest — Pruebas de Renderizado de la Capa Frontend Blade
 *
 * Valida que todas las rutas web del frontend respondan correctamente (HTTP 200),
 * carguen las vistas Blade correspondientes y cumplan los contratos de interfaz.
 */
class FrontendRoutesTest extends TestCase
{
    /**
     * La ruta raíz '/' debe devolver HTTP 200 y renderizar la vista de verificación.
     */
    public function test_ruta_raiz_renderiza_vista_de_verificacion(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('verificacion.index');
        $response->assertSee('Verificar Contenido');
        $response->assertSee('DAGAZ');
    }

    /**
     * La ruta '/verificar' debe devolver HTTP 200 y contener los elementos del mockup CU06.
     */
    public function test_ruta_verificar_renderiza_correctamente(): void
    {
        $response = $this->get('/verificar');

        $response->assertStatus(200);
        $response->assertViewIs('verificacion.index');
        $response->assertSee('form-verificar', false);
        $response->assertSee('tab-texto', false);
        $response->assertSee('tab-url', false);
        $response->assertSee('tab-archivo', false);
        $response->assertSee('fase-procesando', false);
        $response->assertSee('data-modulo="frontend"', false);
    }

    /**
     * La ruta '/verificar/{id}' con UUID válido debe renderizar la vista de resultado.
     */
    public function test_ruta_resultado_con_uuid_renderiza_correctamente(): void
    {
        $uuid = Str::uuid()->toString();
        $response = $this->get("/verificar/{$uuid}");

        $response->assertStatus(200);
        $response->assertViewIs('verificacion.resultado');
        $response->assertViewHas('id', $uuid);
        $response->assertSee($uuid);
        $response->assertSee('bloque-veredicto', false);
        $response->assertSee('btn-compartir', false);
        $response->assertSee('btn-guardar', false);
        $response->assertSee('filtro-notas-grupo', false);
        $response->assertSee('btn-cancelar-nota', false);
        $response->assertSee('panel-editorial', false);
        $response->assertSee('form-editorial', false);
    }

    /**
     * La ruta '/verificar/{id}' con un formato no UUID debe retornar 404 (filtro whereUuid).
     */
    public function test_ruta_resultado_con_id_no_uuid_retorna_404(): void
    {
        $response = $this->get('/verificar/id-invalido-no-uuid');

        $response->assertStatus(404);
    }

    /**
     * La ruta '/historial' debe devolver HTTP 200 y renderizar la vista CU04.
     */
    public function test_ruta_historial_renderiza_correctamente(): void
    {
        $response = $this->get('/historial');

        $response->assertStatus(200);
        $response->assertViewIs('historial.index');
        $response->assertSee('Historial de Noticias');
        $response->assertSee('lista-historial', false);
        $response->assertSee('filtro-veredicto', false);
    }

    /**
     * La ruta '/fuentes' debe devolver HTTP 200 y renderizar el catálogo CU07.
     */
    public function test_ruta_fuentes_renderiza_correctamente(): void
    {
        $response = $this->get('/fuentes');

        $response->assertStatus(200);
        $response->assertViewIs('fuentes.index');
        $response->assertSee('Grafo de Credibilidad');
        $response->assertSee('lista-fuentes', false);
        $response->assertSee('filtro-nivel', false);
    }

    /**
     * La ruta '/perfil' debe devolver HTTP 200 y contener garantías de la Ley 1581.
     */
    public function test_ruta_perfil_renderiza_correctamente(): void
    {
        $response = $this->get('/perfil');

        $response->assertStatus(200);
        $response->assertViewIs('perfil.index');
        $response->assertSee('Mi Cuenta');
        $response->assertSee('1581');
        $response->assertSee('btn-descargar-datos', false);
        $response->assertSee('dialogo-eliminar', false);
    }

    /**
     * La ruta '/login' debe devolver HTTP 200 y renderizar la vista CU05.
     */
    public function test_ruta_login_renderiza_correctamente(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
        $response->assertSee('Iniciar Sesión');
        $response->assertSee('form-login', false);
        $response->assertSee('email');
        $response->assertSee('password');
    }

    /**
     * La ruta '/registro' debe devolver HTTP 200 y contener la casilla de Ley 1581.
     */
    public function test_ruta_registro_renderiza_correctamente(): void
    {
        $response = $this->get('/registro');

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
        $response->assertSee('Crear Cuenta');
        $response->assertSee('acepta_datos', false);
        $response->assertSee('1581');
    }

    /**
     * Las vistas deben incluir meta tags de accesibilidad y viewport para móviles.
     */
    public function test_vistas_incluyen_meta_tags_moviles_y_barra_navegacion(): void
    {
        $response = $this->get('/verificar');

        $response->assertStatus(200);
        $response->assertSee('viewport-fit=cover', false);
        $response->assertSee('manifest.webmanifest', false);
        $response->assertSee('btn-tamano-normal', false);
        $response->assertSee('btn-tamano-grande', false);
        $response->assertSee('Navegación principal', false);
    }
}
