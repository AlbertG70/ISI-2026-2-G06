<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LogComunicadorApi;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * PartnerApiService — Gestión de Autenticación y Gobernanza para Redacciones Socias
 *
 * TSK-4.2 (Sprint 4 / REQ-16): API pública v1 para salas de prensa aliadas.
 * Controla la validación de API Keys, rate limits por socio y resolución de identidad.
 *
 * @package App\Services
 */
class PartnerApiService
{
    /**
     * Valida si una API Key provista es válida y está activa en el sistema.
     *
     * @param  string|null  $apiKey
     * @return bool
     */
    public function esKeyValida(?string $apiKey): bool
    {
        if (empty($apiKey)) {
            return false;
        }

        $socio = $this->obtenerSocio($apiKey);

        return $socio !== null && ($socio['activo'] ?? false) === true;
    }

    /**
     * Obtiene los metadatos del socio asociado a una API Key.
     *
     * @param  string  $apiKey
     * @return array{id_socio: string, nombre: string, email_contacto: string, rate_limit_minute: int, activo: bool, permisos: list<string>}|null
     */
    public function obtenerSocio(string $apiKey): ?array
    {
        $keys = config('dagaz.partners.keys', []);

        return $keys[$apiKey] ?? null;
    }

    /**
     * Verifica si el socio posee un permiso específico (ej. 'verificar', 'consultar', 'fuentes').
     *
     * @param  string  $apiKey
     * @param  string  $permiso
     * @return bool
     */
    public function tienePermiso(string $apiKey, string $permiso): bool
    {
        $socio = $this->obtenerSocio($apiKey);

        if (! $socio || ! ($socio['activo'] ?? false)) {
            return false;
        }

        $permisos = $socio['permisos'] ?? [];

        return in_array('*', $permisos, true) || in_array($permiso, $permisos, true);
    }

    /**
     * Obtiene o aprovisiona de forma idempotente el Usuario de tipo 'editor'
     * asociado al socio en MariaDB para trazabilidad de veredictos y notas.
     *
     * @param  array  $socio
     * @return Usuario
     */
    public function obtenerUsuarioAsociado(array $socio): Usuario
    {
        $email = $socio['email_contacto'] ?? "{$socio['id_socio']}@partner.dagaz.com";

        return Usuario::firstOrCreate(
            ['email' => $email],
            [
                'id_usuario'    => (string) Str::uuid(),
                'password_hash' => bcrypt(Str::random(32)),
                'rol'           => 'editor',
            ]
        );
    }

    /**
     * Registra auditoría técnica en la tabla logs_comunicador_api para llamadas de partners.
     *
     * @param  string       $idSocio
     * @param  string       $tipoOperacion
     * @param  int          $codigoRespuesta
     * @param  string|null  $detalleError
     * @param  string|null  $idProceso
     * @return void
     */
    public function registrarAuditoria(
        string $idSocio,
        string $tipoOperacion,
        int $codigoRespuesta,
        ?string $detalleError = null,
        ?string $idProceso = null
    ): void {
        try {
            LogComunicadorApi::create([
                'id_proceso'       => $idProceso,
                'servicio'         => "PartnerAPI:{$idSocio}",
                'tipo_operacion'   => $tipoOperacion,
                'codigo_respuesta' => $codigoRespuesta,
                'detalle_error'    => $detalleError ? substr($detalleError, 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[PartnerApiService] No se pudo registrar log de partner: ' . $e->getMessage());
        }
    }
}
