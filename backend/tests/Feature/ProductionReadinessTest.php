<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Adapters\AdaptadorMulticanal;
use App\Adapters\Ia\IaDriverFactory;
use App\Adapters\Ia\OpenAiDriver;
use App\Models\Fuente;
use App\Providers\AppServiceProvider;
use App\Services\MultiModeloHarnessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * ProductionReadinessTest — Pruebas de Preparación Pre-Producción (Opción A)
 *
 * Verifica:
 *  - Detección de proxies inversos (TrustProxies + config/trustedproxy.php)
 *  - Forzado condicional de esquema HTTPS (AppServiceProvider + config/dagaz.php)
 *  - Configuración CORS dinámica por variables de entorno (config/cors.php)
 *  - Endpoints /health y /api/health con verificación real de BD
 *  - Inyección dinámica del servidor activo en /api/docs
 *  - Desactivación segura de microservicios opcionales OCR/STT
 *  - Lectura estricta desde config() (compatible con php artisan config:cache)
 *  - Búsqueda de fuentes case-insensitive compatible con SQLite/MariaDB/PostgreSQL
 */
class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoints_reportan_estado_de_base_de_datos_y_metadatos(): void
    {
        $apiResponse = $this->getJson('/api/health');

        $apiResponse->assertStatus(200)
            ->assertJsonPath('estado', 'operativo')
            ->assertJsonPath('sistema', 'DAGAZ')
            ->assertJsonPath('version', (string) config('dagaz.version', '0.0.24'))
            ->assertJsonPath('base_datos', 'conectada')
            ->assertJsonPath('entorno', 'testing')
            ->assertJsonStructure(['estado', 'sistema', 'version', 'base_datos', 'entorno', 'timestamp']);

        $webResponse = $this->getJson('/health');

        $webResponse->assertStatus(200)
            ->assertJsonPath('estado', 'operativo')
            ->assertJsonPath('sistema', 'DAGAZ')
            ->assertJsonPath('base_datos', 'conectada')
            ->assertJsonStructure(['estado', 'sistema', 'version', 'base_datos', 'timestamp']);
    }

    public function test_trust_proxies_detecta_https_y_host_publico_en_docs_y_respeta_desactivacion(): void
    {
        $originalProxies = config('trustedproxy.proxies');

        try {
            config(['trustedproxy.proxies' => '*']);

            $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->withHeaders([
                    'X-Forwarded-Proto' => 'https',
                    'X-Forwarded-Host'  => 'dagaz.up.railway.app',
                    'X-Forwarded-Port'  => '443',
                ])
                ->get('/api/docs');

            $response->assertStatus(200);
            $contenido = $response->getContent();
            $this->assertStringContainsString('openapi: 3.1.0', $contenido);
            $this->assertStringContainsString('url: https://dagaz.up.railway.app/api/v1', $contenido);
            $this->assertStringContainsString('url: http://localhost:8000/api/v1', $contenido);

            // Al restringir proxies a IP distinta de REMOTE_ADDR, ignora cabeceras X-Forwarded-*
            config(['trustedproxy.proxies' => ['192.0.2.10']]);

            $untrustedResponse = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
                ->withHeaders([
                    'X-Forwarded-Proto' => 'https',
                    'X-Forwarded-Host'  => 'atacante.ejemplo.com',
                    'X-Forwarded-Port'  => '443',
                ])
                ->get('/api/docs');

            $untrustedResponse->assertStatus(200);
            $this->assertStringNotContainsString('atacante.ejemplo.com', $untrustedResponse->getContent());

            // Si el host coincide exactamente con localhost:8000 no duplica el bloque servers
            config(['trustedproxy.proxies' => '*']);

            $localResponse = $this->get('http://localhost:8000/api/docs');
            $localResponse->assertStatus(200);
            $this->assertSame(1, substr_count($localResponse->getContent(), 'url: http://localhost:8000/api/v1'));
        } finally {
            config(['trustedproxy.proxies' => $originalProxies]);
        }
    }

    public function test_config_trustedproxy_y_cors_parsean_variables_de_entorno(): void
    {
        $this->assertSame('*', config('trustedproxy.proxies'));
        $this->assertSame(['*'], config('cors.allowed_origins'));
        $this->assertFalse(config('cors.supports_credentials'));
        $this->assertSame(3600, config('cors.max_age'));

        $script = implode(' ', [
            'require "vendor/autoload.php";',
            '$tp = require "config/trustedproxy.php";',
            '$cors = require "config/cors.php";',
            'echo json_encode(["tp" => $tp, "cors" => $cors]);',
        ]);

        // Caso 1: CSV de proxies/orígenes, barra final limpiada y credenciales en texto 'false'
        $procRestricted = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'TRUSTED_PROXIES'           => '10.0.0.1, 172.16.0.0/12',
            'CORS_ALLOWED_ORIGINS'      => 'https://dagaz.up.railway.app/, https://app.dagaz.co',
            'CORS_SUPPORTS_CREDENTIALS' => 'false',
            'CORS_MAX_AGE'              => '7200',
        ]);
        $procRestricted->mustRun();
        $dataRestricted = json_decode($procRestricted->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(['10.0.0.1', '172.16.0.0/12'], $dataRestricted['tp']['proxies']);
        $this->assertSame(['https://dagaz.up.railway.app', 'https://app.dagaz.co'], $dataRestricted['cors']['allowed_origins']);
        $this->assertFalse($dataRestricted['cors']['supports_credentials']);
        $this->assertSame(7200, $dataRestricted['cors']['max_age']);

        // Caso 2: Desactivar proxies ('none') y habilitar credenciales ('true')
        $procNone = new Process([PHP_BINARY, '-r', $script], base_path(), [
            'TRUSTED_PROXIES'           => 'none',
            'CORS_ALLOWED_ORIGINS'      => '*',
            'CORS_SUPPORTS_CREDENTIALS' => 'true',
        ]);
        $procNone->mustRun();
        $dataNone = json_decode($procNone->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([], $dataNone['tp']['proxies']);
        $this->assertSame(['*'], $dataNone['cors']['allowed_origins']);
        $this->assertTrue($dataNone['cors']['supports_credentials']);
    }

    public function test_app_service_provider_fuerza_https_segun_app_url_o_force_https(): void
    {
        $originalForceHttps = config('dagaz.force_https');
        $originalAppUrl     = config('app.url');
        $wasStrict          = Model::preventsLazyLoading();
        $provider           = new AppServiceProvider($this->app);

        try {
            URL::forceScheme(null);
            config(['dagaz.force_https' => null, 'app.url' => 'http://localhost:8000']);
            $provider->boot();
            $this->assertStringStartsWith('http://', url('/api/health'));

            // En automático (null) + APP_URL https:// => fuerza HTTPS
            URL::forceScheme(null);
            config(['dagaz.force_https' => null, 'app.url' => 'https://dagaz.up.railway.app']);
            $provider->boot();
            $this->assertStringStartsWith('https://', url('/api/health'));

            // Con APP_FORCE_HTTPS=false explícito => respeta HTTP incluso si APP_URL dice https://
            URL::forceScheme(null);
            config(['dagaz.force_https' => false, 'app.url' => 'https://dagaz.up.railway.app']);
            $provider->boot();
            $this->assertStringStartsWith('http://', url('/api/health'));

            // Con APP_FORCE_HTTPS=true explícito => fuerza HTTPS incluso en URL local
            URL::forceScheme(null);
            config(['dagaz.force_https' => true, 'app.url' => 'http://localhost:8000']);
            $provider->boot();
            $this->assertStringStartsWith('https://', url('/api/health'));
        } finally {
            URL::forceScheme(null);
            Model::shouldBeStrict($wasStrict);
            config([
                'dagaz.force_https' => $originalForceHttps,
                'app.url'           => $originalAppUrl,
            ]);
        }
    }

    public function test_adaptador_multicanal_omite_llamadas_http_si_ocr_o_stt_estan_deshabilitados(): void
    {
        $originalOcr = config('dagaz.ocr_service_url');
        $originalStt = config('dagaz.stt_service_url');

        try {
            Http::fake();
            Http::preventStrayRequests();

            /** @var AdaptadorMulticanal $adaptador */
            $adaptador = app(AdaptadorMulticanal::class);

            foreach (['', 'false', 'none', 'disabled', 'off'] as $valorDeshabilitado) {
                config([
                    'dagaz.ocr_service_url' => $valorDeshabilitado,
                    'dagaz.stt_service_url' => $valorDeshabilitado,
                ]);

                $textoOcr = $adaptador->extraerTextoOCR('captura_noticia_prueba.png');
                $textoStt = $adaptador->transcribirAudio('nota_voz_prueba.ogg');

                $this->assertNotEmpty($textoOcr);
                $this->assertNotEmpty($textoStt);
            }

            Http::assertNothingSent();
        } finally {
            config([
                'dagaz.ocr_service_url' => $originalOcr,
                'dagaz.stt_service_url' => $originalStt,
            ]);
        }
    }

    public function test_multi_modelo_harness_e_ia_factory_leen_configuracion_compatible_con_config_cache(): void
    {
        $originalConfig = config('dagaz');
        $originalAppUrl = config('app.url');

        try {
            config([
                'app.url'                                           => 'https://dagaz-prod.example.com',
                'dagaz.ia_proveedor'                                => 'openrouter',
                'dagaz.openai.api_key'                              => '',
                'dagaz.gemini.api_key'                              => '',
                'dagaz.anthropic.api_key'                           => '',
                'dagaz.openrouter.api_key'                          => 'sk-or-v1-config-cache-test',
                'dagaz.openrouter.modelo'                           => 'cohere/config-cache-model:free',
                'dagaz.multi_modelo.habilitado'                     => true,
                'dagaz.multi_modelo.qwen_activo'                    => true,
                'dagaz.multi_modelo.openrouter_modelos.agente_4'    => 'deepseek/config-test-4:free',
                'dagaz.multi_modelo.openrouter_modelos.agente_5'    => 'qwen/config-test-5:free',
            ]);

            $driver = IaDriverFactory::crear();
            $this->assertInstanceOf(OpenAiDriver::class, $driver);

            Http::fake([
                'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'veredicto'             => 'FALSO',
                                    'confianza'             => 91,
                                    'acuerdo_con_propuesto' => true,
                                    'critica'               => 'Verificación sintética desde config().',
                                ]),
                            ],
                        ],
                    ],
                ], 200),
            ]);

            /** @var MultiModeloHarnessService $harness */
            $harness = app(MultiModeloHarnessService::class);
            $resultado = $harness->evaluarConConsejo('Afirmación de prueba', 'FALSO');

            $this->assertTrue($resultado['habilitado']);
            $this->assertSame(5, $resultado['total_consultados']);
            $this->assertSame(5, $resultado['total_respondieron']);
            $this->assertSame('FALSO', $resultado['veredicto_consenso']);

            Http::assertSent(fn ($request) =>
                $request->hasHeader('HTTP-Referer', 'https://dagaz-prod.example.com')
                && ($request->data()['model'] ?? null) === 'deepseek/config-test-4:free'
            );

            Http::assertSent(fn ($request) =>
                $request->hasHeader('HTTP-Referer', 'https://dagaz-prod.example.com')
                && ($request->data()['model'] ?? null) === 'qwen/config-test-5:free'
            );
        } finally {
            config([
                'dagaz'   => $originalConfig,
                'app.url' => $originalAppUrl,
            ]);
        }
    }

    public function test_busqueda_de_fuentes_funciona_sin_sensibilidad_a_mayusculas(): void
    {
        Fuente::create([
            'nombre'              => 'Reuters Internacional Test',
            'url_sitio'           => 'https://reuters.example.com',
            'tipo_fuente'         => 'PERIODISTICO',
            'indice_credibilidad' => 92.5,
        ]);

        Fuente::create([
            'nombre'              => 'Blog Desinformador XYZ',
            'url_sitio'           => 'https://blog-xyz.example.com',
            'tipo_fuente'         => 'OTRO',
            'indice_credibilidad' => 15.0,
        ]);

        $publica = $this->getJson('/api/v1/fuentes?buscar=reuters');
        $publica->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('fuentes.0.nombre', 'Reuters Internacional Test');

        $partner = $this->withHeader('X-API-KEY', 'dagaz_partner_test_key_secret_12345')
            ->getJson('/api/v1/public/fuentes?buscar=REUTERS');
        $partner->assertStatus(200)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('fuentes.0.nombre', 'Reuters Internacional Test');
    }
}
