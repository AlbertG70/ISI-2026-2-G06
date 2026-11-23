<?php

namespace App\Providers;

use App\Adapters\AdaptadorMulticanal;
use App\Adapters\Ia\IaDriverFactory;
use App\Contracts\AdaptadorApiIaInterface;
use App\Contracts\AdaptadorMulticanalInterface;
use App\Contracts\ConectorArchiveInterface;
use App\Services\ArchiveOrgService;
use App\Services\CircuitBreakerService;
use App\Services\VerificacionService;
use Illuminate\Support\ServiceProvider;

/**
 * AppServiceProvider — Proveedor de Servicios de la Aplicación DAGAZ
 *
 * Registra los enlaces de interfaces a implementaciones concretas
 * siguiendo el principio de inversión de dependencias (DIP).
 *
 * Patrón Strategy: AdaptadorApiIaInterface se resuelve dinámicamente
 * según la variable de entorno DAGAZ_IA_PROVEEDOR.
 *
 * @package App\Providers
 */
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Vincula las interfaces del DCD con sus implementaciones concretas:
     * - AdaptadorApiIaInterface → Driver activo según config('dagaz.ia_proveedor')
     * - AdaptadorMulticanalInterface → AdaptadorMulticanal
     * - ConectorArchiveInterface → ArchiveOrgService (TSK-3.2 & REQ-10)
     * - VerificacionService → Singleton en el contenedor
     */
    public function register(): void
    {
        // Patrón Strategy: el driver de IA se resuelve dinámicamente
        $this->app->bind(AdaptadorApiIaInterface::class, function () {
            return IaDriverFactory::crear();
        });

        // Adaptador multicanal (OCR, STT, scraping)
        $this->app->bind(AdaptadorMulticanalInterface::class, AdaptadorMulticanal::class);

        // Conector Archive.org / Wayback Machine (TSK-3.2 & REQ-10)
        $this->app->bind(ConectorArchiveInterface::class, ArchiveOrgService::class);

        // Servicio de recuperación y análisis interlingüe (TSK-4.4 & REQ-19)
        $this->app->bind(\App\Contracts\ServicioInterlingueInterface::class, function ($app) {
            $adaptadorIa = null;
            try {
                $adaptadorIa = $app->make(AdaptadorApiIaInterface::class);
            } catch (\Throwable) {
                // Graceful fallback heurístico si las credenciales de IA no están presentes
            }

            return new \App\Services\ServicioInterlingue(
                adaptadorIa: $adaptadorIa
            );
        });

        // Servicio de verificación y arbitraje con consejo multi-modelo IA
        $this->app->singleton(\App\Services\MultiModeloHarnessService::class, function () {
            return new \App\Services\MultiModeloHarnessService();
        });

        // Servicio de búsqueda web en tiempo real (cotizaciones, volatilidad y noticias vivas)
        $this->app->singleton(\App\Services\BusquedaWebService::class, function () {
            return new \App\Services\BusquedaWebService();
        });

        // VerificacionService como singleton para reutilizar en el ciclo de vida HTTP
        $this->app->singleton(VerificacionService::class, function ($app) {
            return new VerificacionService(
                adaptadorMulticanal:        $app->make(AdaptadorMulticanal::class),
                circuitBreakerService:      $app->make(CircuitBreakerService::class),
                archiveOrgService:          $app->make(ConectorArchiveInterface::class),
                servicioInterlingue:        $app->make(\App\Contracts\ServicioInterlingueInterface::class),
                busquedaSemanticaService:   $app->make(\App\Services\BusquedaSemanticaService::class),
                multiModeloHarnessService:  $app->make(\App\Services\MultiModeloHarnessService::class),
                busquedaWebService:         $app->make(\App\Services\BusquedaWebService::class),
            );
        });

        // Servicio de API pública para redacciones socias (TSK-4.2 & REQ-16)
        $this->app->singleton(\App\Services\PartnerApiService::class, function () {
            return new \App\Services\PartnerApiService();
        });

        // Servicio de exportación de auditoría forense para reguladores y medios (TSK-4.6 & REQ-25)
        $this->app->singleton(\App\Services\AuditoriaExportService::class, function () {
            return new \App\Services\AuditoriaExportService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Forzar HTTPS al ejecutar en producción (detrás de TLS proxy PaaS) o cuando APP_URL usa https://
        $forceHttps = config('dagaz.force_https');
        $appUrl = (string) config('app.url', '');
        if (
            $forceHttps === true ||
            ($forceHttps === null && ($this->app->isProduction() || str_starts_with(strtolower($appUrl), 'https://')))
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Configurar Eloquent para que use MariaDB/PostgreSQL correctamente
        // con UUID como tipo de clave primaria en toda la aplicación
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(
            ! $this->app->isProduction()
        );

        // Rate Limiting para Redacciones Socias (TSK-4.2 / REQ-16)
        \Illuminate\Support\Facades\RateLimiter::for('partners', function (\Illuminate\Http\Request $request) {
            $socio = $request->attributes->get('socio');
            $limite = $socio['rate_limit_minute'] ?? 60;
            $identificador = $socio['id_socio'] ?? $request->ip();

            return \Illuminate\Cache\RateLimiting\Limit::perMinute($limite)->by((string) $identificador);
        });
    }
}
