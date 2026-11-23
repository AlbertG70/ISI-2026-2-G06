<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BusquedaWebService — Búsqueda Web en Tiempo Real para Temas de Actualidad
 *
 * Provee verificación en vivo para tópicos de alta volatilidad temporal
 * (precio del dólar/TRM, petróleo Brent/WTI, cotización de acciones,
 * inflación, política reciente y declaraciones del día), superando la
 * limitación de un corpus RAG cerrado y desactualizado.
 *
 * @package App\Services
 */
class BusquedaWebService
{
    private const TIMEOUT_SEGUNDOS = 5;

    /**
     * Patrones léxicos que señalan requerimiento de búsqueda web en tiempo real.
     */
    private const PATRONES_VOLATILES = [
        // Divisas y tasas
        '/\b(dolar|dólar|trm|pesos\s+por\s+dolar|tasa\s+de\s+cambio|euro|divisa|divisas)\b/iu',
        // Petróleo y energía
        '/\b(petroleo|petróleo|brent|wti|barril\s+de\s+(?:crudo|petroleo|petróleo)|gasolina|acpm|diesel)\b/iu',
        // Mercados y finanzas
        '/\b(accion|acción|acciones|ecopetrol|bvc|bolsa\s+de\s+valores|wall\s+street|s&p|dow\s+jones|nasdaq)\b/iu',
        // Indicadores macroeconómicos
        '/\b(inflacion|inflación|ipc|tasa\s+de\s+interes|tasas\s+de\s+interés|desempleo|pib)\b/iu',
        // Temporalidad inmediata
        '/\b(hoy|ayer|esta\s+semana|este\s+mes|en\s+este\s+momento|actualmente|última\s+hora|reciente|alcanzo\s+hoy|coto\s+hoy)\b/iu',
        // Política de última hora
        '/\b(anunció\s+hoy|decreto\s+hoy|nuevo\s+ministro|renuncio|renunció|designado|nombrado)\b/iu',
    ];

    /**
     * Determina si la afirmación o contenido hace referencia a temas volátiles o de actualidad.
     */
    public function esTemaActualOVolatil(string $texto): bool
    {
        foreach (self::PATRONES_VOLATILES as $patron) {
            if (preg_match($patron, $texto)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ejecuta una búsqueda web ligera y extrae evidencias documentales en tiempo real.
     *
     * @param  string $texto   Afirmación o términos clave a buscar.
     * @param  int    $limite  Máximo de resultados a retornar.
     * @return array<int, array{titulo: string, url: string, fragmento: string, fuente: string}>
     */
    public function buscarEnLaWeb(string $texto, int $limite = 4): array
    {
        $consulta = $this->construirConsultaWeb($texto);
        if (empty($consulta)) {
            return [];
        }

        $resultados = [];

        try {
            $queryUrl = urlencode($consulta);
            $req = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36 DAGAZ-Bot/1.0',
                'Accept'     => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            ])->timeout(self::TIMEOUT_SEGUNDOS);

            if (app()->environment('local', 'testing') || DIRECTORY_SEPARATOR === '\\') {
                $req = $req->withoutVerifying();
            }

            $resp = $req->get("https://html.duckduckgo.com/html/?q={$queryUrl}");

            if ($resp->successful()) {
                $html = $resp->body();

                // Extraer resultados HTML de DuckDuckGo
                preg_match_all(
                    '/<a class="result__url[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>.*?<a class="result__snippet[^>]*>(.*?)<\/a>/s',
                    $html,
                    $matches
                );

                $total = count($matches[3] ?? []);
                for ($i = 0; $i < min($limite, $total); $i++) {
                    $rawUrl = trim(strip_tags($matches[1][$i]));
                    $url = $this->desempacarUrl($rawUrl);
                    $snippet = trim(strip_tags(html_entity_decode($matches[3][$i])));
                    $host = parse_url($url, PHP_URL_HOST) ?? 'Web Verificada';

                    if (!empty($snippet) && !empty($url)) {
                        $resultados[] = [
                            'titulo'    => "Evidencia Web: {$host}",
                            'url'       => $url,
                            'fragmento' => $snippet,
                            'fuente'    => $host,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[BusquedaWebService] Error en consulta web: ' . $e->getMessage(), [
                'consulta' => $consulta,
            ]);
        }

        return $resultados;
    }

    /**
     * Construye una consulta de búsqueda optimizada a partir del texto de la afirmación.
     */
    private function construirConsultaWeb(string $texto): string
    {
        // Limpiar puntuaciones redundantes
        $limpio = trim((string) preg_replace('/[^\p{L}\p{N}\s$%]/u', ' ', $texto));
        $limpio = (string) preg_replace('/\s+/', ' ', $limpio);

        // Si es muy larga, tomar hasta las primeras 12 palabras clave
        $palabras = explode(' ', $limpio);
        if (count($palabras) > 12) {
            $limpio = implode(' ', array_slice($palabras, 0, 12));
        }

        return $limpio;
    }

    /**
     * Desempaca URLs con redirect de DuckDuckGo si aplican.
     */
    private function desempacarUrl(string $url): string
    {
        if (str_contains($url, 'uddg=')) {
            $parsed = parse_url($url, PHP_URL_QUERY);
            if ($parsed) {
                parse_str($parsed, $params);
                if (!empty($params['uddg'])) {
                    return (string) $params['uddg'];
                }
            }
        }

        return $url;
    }
}
