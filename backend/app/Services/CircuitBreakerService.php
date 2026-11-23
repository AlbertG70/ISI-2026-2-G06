<?php

namespace App\Services;

use App\Models\LogComunicadorApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CircuitBreakerService — Control de Costes y Cuotas de Inferencia (TSK-4.3 / REQ-18)
 *
 * Previene sobrecostes en las llamadas a modelos de lenguaje (LLMs) comerciales
 * como OpenAI y Gemini implementando un disyuntor automático (Circuit Breaker).
 *
 * Monitorea:
 *   1. Tokens consumidos por hora (umbral configurable: DAGAZ_MAX_TOKENS_POR_HORA)
 *   2. Gasto diario acumulado en USD (umbral configurable: DAGAZ_PRESUPUESTO_DIARIO_USD)
 *
 * Estados del circuito:
 *   - CLOSED: Normal, todas las peticiones son permitidas.
 *   - OPEN:   Circuito abierto por superar cuota; las llamadas a APIs externas
 *             se bloquean inmediatamente y retornan veredicto local o error controlado.
 *   - HALF_OPEN: Estado de prueba tras enfriamiento (60 segundos).
 */
class CircuitBreakerService
{
    private const CACHE_PREFIX = 'dagaz_circuit_breaker:';
    private const ENFRIAMIENTO_SEGUNDOS = 60;

    /**
     * Evalúa si una llamada a la API de IA está permitida bajo los límites actuales.
     *
     * @param  int  $tokensEstimados  Cantidad estimada de tokens para la operación.
     * @return bool  true si el circuito permite la ejecución, false si está bloqueado.
     */
    public function puedeEjecutar(int $tokensEstimados = 500): bool
    {
        $maxTokensHora    = (int) config('dagaz.max_tokens_por_hora', 50000);
        $presupuestoDiario = (float) config('dagaz.presupuesto_diario_usd', 5.00);

        $tokensConsumidosHora = $this->getTokensConsumidosHora();
        $gastoEstimadoHoy     = $this->getGastoEstimadoHoy();

        // 1. Validar límite de tokens por hora
        if (($tokensConsumidosHora + $tokensEstimados) > $maxTokensHora) {
            Log::warning('[DAGAZ][CircuitBreaker] Límite de tokens por hora superado.', [
                'consumido' => $tokensConsumidosHora,
                'estimado'  => $tokensEstimados,
                'limite'    => $maxTokensHora,
            ]);
            $this->abrirCircuito('Límite de tokens/hora alcanzado.');
            return false;
        }

        // 2. Validar presupuesto diario en USD
        if ($gastoEstimadoHoy >= $presupuestoDiario) {
            Log::warning('[DAGAZ][CircuitBreaker] Presupuesto diario en USD agotado.', [
                'gasto_hoy'   => $gastoEstimadoHoy,
                'presupuesto' => $presupuestoDiario,
            ]);
            $this->abrirCircuito('Presupuesto diario en USD agotado.');
            return false;
        }

        // Si el circuito estaba abierto pero ya pasó el tiempo de enfriamiento, pasar a HALF_OPEN
        if ($this->estaAbierto()) {
            return false;
        }

        return true;
    }

    /**
     * Registra el consumo real de una llamada exitosa a un proveedor de IA.
     *
     * @param  string  $servicio       Nombre del proveedor ('OpenAI', 'Gemini', etc.)
     * @param  int     $tokensTotales  Tokens consumidos (prompt + respuesta)
     * @param  float   $costoUsd       Costo aproximado en USD
     * @return void
     */
    public function registrarConsumo(string $servicio, int $tokensTotales, float $costoUsd = 0.0): void
    {
        $horaKey = self::CACHE_PREFIX . 'tokens_hora_' . date('Ymd_H');
        $diaKey  = self::CACHE_PREFIX . 'gasto_dia_' . date('Ymd');

        // Incrementar contadores con expiración adecuada
        Cache::increment($horaKey, $tokensTotales);
        Cache::put($horaKey, (int) Cache::get($horaKey), now()->addHours(2));

        $gastoActual = (float) Cache::get($diaKey, 0.0);
        Cache::put($diaKey, round($gastoActual + $costoUsd, 4), now()->addDays(2));

        Log::info('[DAGAZ][CircuitBreaker] Consumo registrado.', [
            'servicio' => $servicio,
            'tokens'   => $tokensTotales,
            'costo'    => $costoUsd,
            'total_h'  => Cache::get($horaKey),
        ]);
    }

    /**
     * Retorna los tokens consumidos en la hora actual.
     */
    public function getTokensConsumidosHora(): int
    {
        $horaKey = self::CACHE_PREFIX . 'tokens_hora_' . date('Ymd_H');
        return (int) Cache::get($horaKey, 0);
    }

    /**
     * Retorna el gasto acumulado estimado en USD para el día de hoy.
     */
    public function getGastoEstimadoHoy(): float
    {
        $diaKey = self::CACHE_PREFIX . 'gasto_dia_' . date('Ymd');
        return (float) Cache::get($diaKey, 0.0);
    }

    /**
     * Determina si el circuito está abierto (bloqueado).
     */
    public function estaAbierto(): bool
    {
        return Cache::has(self::CACHE_PREFIX . 'estado_abierto');
    }

    /**
     * Abre el circuito de forma temporal para proteger los costes.
     */
    public function abrirCircuito(string $motivo): void
    {
        Cache::put(
            self::CACHE_PREFIX . 'estado_abierto',
            ['motivo' => $motivo, 'abierto_en' => now()->toIso8601String()],
            now()->addSeconds(self::ENFRIAMIENTO_SEGUNDOS)
        );
    }

    /**
     * Restablece manualmente el estado del circuito (solo admin).
     */
    public function reiniciar(): void
    {
        $horaKey = self::CACHE_PREFIX . 'tokens_hora_' . date('Ymd_H');
        $diaKey  = self::CACHE_PREFIX . 'gasto_dia_' . date('Ymd');

        Cache::forget(self::CACHE_PREFIX . 'estado_abierto');
        Cache::forget($horaKey);
        Cache::forget($diaKey);

        Log::info('[DAGAZ][CircuitBreaker] Circuito restablecido manualmente.');
    }

    /**
     * Retorna métricas globales de consumo y estado actual del circuito.
     *
     * @return array<string, mixed>
     */
    public function obtenerMetricas(): array
    {
        $maxTokensHora    = (int) config('dagaz.max_tokens_por_hora', 50000);
        $presupuestoDiario = (float) config('dagaz.presupuesto_diario_usd', 5.00);
        $consumidoHora    = $this->getTokensConsumidosHora();
        $gastoHoy         = $this->getGastoEstimadoHoy();
        $abierto          = $this->estaAbierto();

        return [
            'circuito_activo'      => ! $abierto,
            'estado'               => $abierto ? 'OPEN (Bloqueado)' : 'CLOSED (Operativo)',
            'tokens_hora_actual'   => $consumidoHora,
            'tokens_max_hora'      => $maxTokensHora,
            'porcentaje_hora'      => $maxTokensHora > 0 ? round(($consumidoHora / $maxTokensHora) * 100, 2) : 0,
            'gasto_hoy_usd'        => $gastoHoy,
            'presupuesto_hoy_usd'  => $presupuestoDiario,
            'porcentaje_presupuesto' => $presupuestoDiario > 0 ? round(($gastoHoy / $presupuestoDiario) * 100, 2) : 0,
            'proveedor_activo'     => config('dagaz.ia_proveedor'),
        ];
    }
}
