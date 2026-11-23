<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\AdaptadorApiIaInterface;
use App\Contracts\ServicioInterlingueInterface;
use App\Models\LogComunicadorApi;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class ServicioInterlingue
 *
 * Implementación del servicio de recuperación, análisis y contraste interlingüe.
 * Requisito: TSK-4.4 & REQ-19 (Traducir y contrastar afirmaciones anglosajonas que circulan en Colombia).
 *
 * Funcionalidades:
 * 1. Detección automática de idioma (Español, Inglés, Mixto) y origen anglosajón.
 * 2. Traducción bidireccional ES <-> EN con preservación terminológica para fact-checking.
 * 3. Detección de distorsión y sensacionalismo agregado en traducciones virales.
 * 4. Generación de consultas en inglés para contrastar con agencias Nivel 1 (Reuters, AP, BBC, OMS).
 * 5. Registro de telemetría y auditoría en `logs_comunicador_api`.
 *
 * @package App\Services
 */
class ServicioInterlingue implements ServicioInterlingueInterface
{
    /**
     * Stopwords representativas del idioma español.
     *
     * @var array<string, bool>
     */
    private const STOPWORDS_ES = [
        'de' => true, 'la' => true, 'que' => true, 'el' => true, 'en' => true,
        'y' => true, 'a' => true, 'los' => true, 'del' => true, 'se' => true,
        'las' => true, 'por' => true, 'un' => true, 'para' => true, 'con' => true,
        'no' => true, 'una' => true, 'su' => true, 'al' => true, 'lo' => true,
        'como' => true, 'mas' => true, 'más' => true, 'pero' => true, 'sus' => true,
        'le' => true, 'ya' => true, 'o' => true, 'fue' => true, 'este' => true,
        'ha' => true, 'si' => true, 'porque' => true, 'esta' => true, 'son' => true,
        'entre' => true, 'cuando' => true, 'muy' => true, 'sin' => true, 'sobre' => true,
        'ser' => true, 'tiene' => true, 'tambien' => true, 'también' => true, 'me' => true,
        'hasta' => true, 'hay' => true, 'donde' => true, 'desde' => true, 'todo' => true,
        'durante' => true, 'todos' => true, 'contra' => true, 'afirma' => true, 'dice' => true,
    ];

    /**
     * Stopwords representativas del idioma inglés.
     *
     * @var array<string, bool>
     */
    private const STOPWORDS_EN = [
        'the' => true, 'of' => true, 'and' => true, 'to' => true, 'a' => true,
        'in' => true, 'that' => true, 'is' => true, 'was' => true, 'for' => true,
        'it' => true, 'with' => true, 'as' => true, 'his' => true, 'on' => true,
        'be' => true, 'at' => true, 'by' => true, 'this' => true, 'have' => true,
        'from' => true, 'or' => true, 'one' => true, 'had' => true, 'by' => true,
        'word' => true, 'but' => true, 'not' => true, 'what' => true, 'all' => true,
        'were' => true, 'we' => true, 'when' => true, 'your' => true, 'can' => true,
        'said' => true, 'there' => true, 'use' => true, 'an' => true, 'each' => true,
        'which' => true, 'she' => true, 'do' => true, 'how' => true, 'their' => true,
        'if' => true, 'will' => true, 'up' => true, 'other' => true, 'about' => true,
        'out' => true, 'many' => true, 'then' => true, 'them' => true, 'these' => true,
        'so' => true, 'some' => true, 'her' => true, 'would' => true, 'make' => true,
        'claims' => true, 'states' => true, 'leaked' => true, 'breaking' => true,
    ];

    /**
     * Diccionario de marcadores de sensacionalismo agregado en traducciones virales en español.
     *
     * @var list<string>
     */
    private const MARCADORES_SENSACIONALISMO_ES = [
        'urgente', 'alerta roja', 'veneno', 'mortal', 'catastrofe', 'catástrofe',
        'secreto revelado', 'ocultan', 'lo que no quieren que sepas', 'confirmado al 100%',
        'macabro', 'aterrador', 'devastador', 'escandalo', 'escándalo', 'peligro inminente',
        'comprobado', 'bomba', 'exclusiva mundial', 'confinamiento forzoso',
    ];

    /**
     * Glosario bilingüe de terminología de fact-checking y desinformación (ES <-> EN).
     *
     * @var array<string, string>
     */
    private const GLOSARIO_TERMINOLOGICO = [
        'breaking news'     => 'última hora',
        'fact check'        => 'verificación de hechos',
        'fake news'         => 'noticia falsa',
        'hoax'              => 'bulo / engaño',
        'whistleblower'     => 'denunciante / testigo clave',
        'lockdown'          => 'confinamiento / cuarentena',
        'vaccine'           => 'vacuna',
        'election fraud'    => 'fraude electoral',
        'conspiracy'        => 'conspiración',
        'deepfake'          => 'ultrafalso / video manipulado',
        'misinformation'    => 'desinformación involuntaria',
        'disinformation'    => 'desinformación deliberada',
        'world health organization' => 'organización mundial de la salud',
        'who'               => 'OMS',
        'united nations'    => 'naciones unidas',
        'un'                => 'ONU',
        'press release'     => 'comunicado de prensa',
        'official statement'=> 'declaración oficial',
        'claims'            => 'afirma',
        'approved'          => 'aprobó',
        'rejected'          => 'rechazó',
        'confirmed'         => 'confirmó',
        'denied'            => 'desmintió',
    ];

    /**
     * @param  \App\Contracts\AdaptadorApiIaInterface|null $adaptadorIa Adaptador de IA activo (opcional)
     */
    public function __construct(
        private readonly ?AdaptadorApiIaInterface $adaptadorIa = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function detectarIdioma(string $texto): array
    {
        $palabras = $this->extraerTokens($texto);
        $totalPalabras = count($palabras);

        if ($totalPalabras === 0) {
            return [
                'codigo'              => 'es',
                'idioma_detectado'    => 'es',
                'nombre'              => 'Español',
                'confianza'           => 1.0,
                'es_anglosajon'       => false,
                'es_mixto'            => false,
                'conteo_palabras'     => 0,
                'proporcion_ingles'   => 0.0,
                'terminos_detectados' => [],
            ];
        }

        $coincidenciasEs = 0;
        $coincidenciasEn = 0;
        $terminosEn = [];

        foreach ($palabras as $p) {
            if (isset(self::STOPWORDS_ES[$p])) {
                $coincidenciasEs++;
            }
            if (isset(self::STOPWORDS_EN[$p])) {
                $coincidenciasEn++;
                if (!isset(self::STOPWORDS_ES[$p])) {
                    $terminosEn[] = $p;
                }
            }
        }

        $terminosUnicosEn = array_values(array_unique($terminosEn));
        $proporcionEn = $totalPalabras > 0 ? round($coincidenciasEn / $totalPalabras, 3) : 0.0;
        $proporcionEs = $totalPalabras > 0 ? round($coincidenciasEs / $totalPalabras, 3) : 0.0;

        $anglicismosPresentes = 0;
        $anglicismosComunes = ['fake', 'news', 'breaking', 'update', 'trending', 'check', 'viral', 'post', 'leak', 'spread', 'spreading'];
        foreach ($anglicismosComunes as $anglo) {
            if (in_array($anglo, $palabras, true)) {
                $anglicismosPresentes++;
            }
        }

        // Determinar idioma principal
        if ($coincidenciasEn > $coincidenciasEs * 1.5 && $coincidenciasEn >= 2) {
            $codigo = 'en';
            $nombre = 'Inglés';
            $esAnglosajon = true;
            $confianza = min(1.0, round($proporcionEn * 2.5, 2));
        } elseif (($coincidenciasEn >= 2 && $coincidenciasEs >= 2) || ($anglicismosPresentes >= 2 && $coincidenciasEs >= 2)) {
            $codigo = 'mixto';
            $nombre = 'Bilingüe (Español / Inglés)';
            $esAnglosajon = true;
            $confianza = 0.85;
        } else {
            $codigo = 'es';
            $nombre = 'Español';
            $esAnglosajon = count($terminosUnicosEn) >= 4 || $proporcionEn >= 0.25;
            $confianza = max(0.60, min(1.0, round(($proporcionEs ?: 0.5) * 2, 2)));
        }

        return [
            'codigo'              => $codigo,
            'idioma_detectado'    => $codigo,
            'nombre'              => $nombre,
            'confianza'           => $confianza,
            'es_anglosajon'       => $esAnglosajon,
            'es_mixto'            => $codigo === 'mixto',
            'conteo_palabras'     => $totalPalabras,
            'proporcion_ingles'   => $proporcionEn,
            'terminos_detectados' => array_slice($terminosUnicosEn, 0, 10),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function traducir(string $texto, string $idiomaDestino = 'es', string $idiomaOrigen = 'auto'): array
    {
        $idiomaDestino = strtolower($idiomaDestino);
        $idiomaOrigen = strtolower($idiomaOrigen);

        if ($idiomaOrigen === 'auto') {
            $det = $this->detectarIdioma($texto);
            $idiomaOrigen = $det['codigo'] === 'en' ? 'en' : 'es';
        }

        // Si origen y destino son iguales, retornar sin modificar
        if ($idiomaOrigen === $idiomaDestino) {
            return [
                'texto_traducido' => $texto,
                'idioma_origen'   => $idiomaOrigen,
                'idioma_destino'  => $idiomaDestino,
                'metodo'          => 'identidad',
            ];
        }

        // 1. Intentar traducción con el Adaptador de IA si está disponible
        if ($this->adaptadorIa) {
            try {
                $nombreDestino = $idiomaDestino === 'en' ? 'inglés (English)' : 'español neutro para Colombia';
                $prompt = "Eres un traductor profesional de fact-checking y noticias de DAGAZ. " .
                          "Traduce fielmente el siguiente texto al {$nombreDestino}. " .
                          "Mantén las cifras exactas, nombres propios y cargos. No agregues interpretaciones ni comentarios. " .
                          "Responde ÚNICAMENTE con el texto traducido:\n\n{$texto}";

                $traduccion = trim($this->adaptadorIa->interpretarPrompt($prompt));

                if (mb_strlen($traduccion) > 5 && !Str::startsWith($traduccion, '[')) {
                    $this->registrarLog(
                        tipoOperacion: 'traduccion_ia',
                        codigoRespuesta: 200,
                        detalleError: null
                    );

                    return [
                        'texto_traducido' => $traduccion,
                        'idioma_origen'   => $idiomaOrigen,
                        'idioma_destino'  => $idiomaDestino,
                        'metodo'          => 'ia_driver',
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('[ServicioInterlingue::traducir] Error al usar adaptador IA: ' . $e->getMessage());
                $this->registrarLog(
                    tipoOperacion: 'traduccion_ia_fallida',
                    codigoRespuesta: 500,
                    detalleError: $e->getMessage()
                );
            }
        }

        // 2. Fallback heurístico mediante sustitución por glosario y preservación estructural
        $traducido = $this->traducirHeuristico($texto, $idiomaDestino);

        $this->registrarLog(
            tipoOperacion: 'traduccion_heuristica',
            codigoRespuesta: 200,
            detalleError: null
        );

        return [
            'texto_traducido' => $traducido,
            'idioma_origen'   => $idiomaOrigen,
            'idioma_destino'  => $idiomaDestino,
            'metodo'          => 'glosario_heuristico',
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function analizarDistorsionTraduccion(string $textoOriginalEn, string $textoTraducidoEs): array
    {
        $tokensEn = $this->extraerTokens($textoOriginalEn);
        $tokensEs = $this->extraerTokens($textoTraducidoEs);

        $discrepancias = [];
        $puntosDistorsion = 0;

        // 1. Detección de sensacionalismo agregado en la versión en español
        $sensacionalismoAgregado = [];
        foreach (self::MARCADORES_SENSACIONALISMO_ES as $marcador) {
            if (mb_stripos($textoTraducidoEs, $marcador) !== false) {
                $sensacionalismoAgregado[] = $marcador;
            }
        }

        if (!empty($sensacionalismoAgregado)) {
            $puntosDistorsion += count($sensacionalismoAgregado) * 20;
            $discrepancias[] = 'Sensacionalismo añadido en español no presente en el original: "' .
                implode('", "', array_slice($sensacionalismoAgregado, 0, 4)) . '".';
        }

        // 2. Detección de cambios de polaridad o certidumbre (de "posible/estudiando" a "confirmado/alerta")
        $marcadoresDudaEn = ['may', 'might', 'could', 'possible', 'preliminary', 'investigating', 'unconfirmed', 'alleged', 'suggest', 'suggests'];
        $marcadoresCertezaEs = ['confirmado', 'confirma', 'confirmaron', 'comprobado', 'comprueba', 'oficialmente', 'demuestra', 'demostrado', 'certeza', 'inminente', 'definitivo', 'definitiva', 'cura'];

        $tieneDudaEn = false;
        foreach ($marcadoresDudaEn as $duda) {
            if (in_array($duda, $tokensEn, true) || mb_stripos($textoOriginalEn, $duda) !== false) {
                $tieneDudaEn = true;
                break;
            }
        }

        $tieneCertezaEs = false;
        foreach ($marcadoresCertezaEs as $certeza) {
            if (in_array($certeza, $tokensEs, true) || mb_stripos($textoTraducidoEs, $certeza) !== false) {
                $tieneCertezaEs = true;
                break;
            }
        }

        if ($tieneDudaEn && $tieneCertezaEs) {
            $puntosDistorsion += 35;
            $discrepancias[] = 'Alteración de certidumbre: El texto original en inglés expresa posibilidad o investigación preliminar, pero la versión traducida afirma certeza categórica.';
        }

        // 3. Falsa atribución institucional (ej. citar a OMS, CDC o Reuters cuando el original no los menciona)
        $organismos = [
            'who' => 'OMS',
            'cdc' => 'CDC',
            'un'  => 'ONU',
            'fda' => 'FDA',
            'reuters' => 'Reuters',
        ];

        foreach ($organismos as $enName => $esName) {
            $enMentioned = mb_stripos($textoOriginalEn, $enName) !== false;
            $esMentioned = mb_stripos($textoTraducidoEs, $esName) !== false;

            if (!$enMentioned && $esMentioned) {
                $puntosDistorsion += 40;
                $discrepancias[] = "Falsa atribución institucional: La versión en español atribuye la afirmación a {$esName}, pero el original no lo cita.";
            }
        }

        $scoreFidelidad = max(0.10, round(1.0 - ($puntosDistorsion / 100.0), 2));
        $distorsionado = $scoreFidelidad < 0.65;

        // Determinar tipo de distorsión principal
        $tipoDistorsion = match (true) {
            !empty($sensacionalismoAgregado) && $tieneDudaEn && $tieneCertezaEs => 'sensacionalismo_y_falsa_certeza',
            !empty($sensacionalismoAgregado) => 'sensacionalismo_agregado',
            $tieneDudaEn && $tieneCertezaEs  => 'alteracion_de_certidumbre',
            $puntosDistorsion >= 40          => 'falsa_atribucion',
            default                          => 'ninguna',
        };

        // Redactar diagnóstico explicativo
        if ($distorsionado) {
            $explicacion = sprintf(
                'Alerta de distorsión interlingüe (REQ-19): La versión en español presenta alteraciones significativas respecto a la fuente anglosajona (Fidelidad: %.0f%%). %s',
                $scoreFidelidad * 100,
                implode(' ', $discrepancias)
            );
        } else {
            $explicacion = sprintf(
                'Traducción congruente: La versión en español mantiene fidelidad semántica adecuada con la fuente original en inglés (Fidelidad: %.0f%%).',
                $scoreFidelidad * 100
            );
        }

        $this->registrarLog(
            tipoOperacion: 'analisis_distorsion',
            codigoRespuesta: 200,
            detalleError: null
        );

        return [
            'distorsionado'                  => $distorsionado,
            'distorsion_detectada'           => $distorsionado,
            'score_fidelidad'                => $scoreFidelidad,
            'score_distorsion'               => round(1.0 - $scoreFidelidad, 2),
            'tipo_distorsion'                => $tipoDistorsion,
            'sensacionalismo_agregado'       => !empty($sensacionalismoAgregado),
            'distorsion_certeza'             => $tieneDudaEn && $tieneCertezaEs,
            'atribucion_institucional_falsa' => ($puntosDistorsion >= 40),
            'terminos_alarmistas_agregados'  => array_values(array_unique($sensacionalismoAgregado)),
            'explicacion'                    => $explicacion,
            'discrepancias'                  => $discrepancias,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function generarConsultasInterlingues(string $afirmacionEs): array
    {
        $palabras = $this->extraerTokens($afirmacionEs);

        // Filtrar stopwords para conservar palabras de significado clave
        $clave = array_values(array_filter($palabras, fn ($p) => !isset(self::STOPWORDS_ES[$p]) && mb_strlen($p) > 3));

        // Mapear términos clave mediante glosario al inglés
        $claveEn = [];
        foreach ($clave as $palabra) {
            $encontrado = false;
            foreach (self::GLOSARIO_TERMINOLOGICO as $en => $es) {
                if (mb_stripos($es, $palabra) !== false || mb_stripos($palabra, $es) !== false) {
                    $claveEn[] = $en;
                    $encontrado = true;
                    break;
                }
            }
            if (!$encontrado) {
                // Conservar palabras no stopwords (como nombres propios: Petro, Colombia, Bogotá)
                $claveEn[] = $palabra;
            }
        }

        $claveEnUnicos = array_values(array_unique($claveEn));
        $cadenaEn = implode(' ', array_slice($claveEnUnicos, 0, 6));

        $consultaPrincipal = $cadenaEn ? "{$cadenaEn} fact check" : "Colombia news fact check";

        $consultasAlternativas = [
            "Reuters Fact Check {$cadenaEn}",
            "AP Fact Check {$cadenaEn}",
            "BBC Reality Check {$cadenaEn}",
            "WHO official statement {$cadenaEn}",
        ];

        return [
            'consulta_principal_en'  => trim($consultaPrincipal),
            'consultas_alternativas' => $consultasAlternativas,
            'palabras_clave_en'      => array_slice($claveEnUnicos, 0, 8),
            'fuentes_recomendadas'   => [
                'Reuters Fact Check (Nivel 1)',
                'Associated Press News Fact Check (Nivel 1)',
                'BBC Reality Check / Verify (Nivel 1)',
                'World Health Organization (OMS Newsroom)',
                'Snopes / IFCN Verified',
            ],
        ];
    }

    /**
     * Extrae un array de palabras en minúsculas sin signos de puntuación.
     *
     * @param  string $texto
     * @return array<int, string>
     */
    private function extraerTokens(string $texto): array
    {
        $limpio = mb_strtolower(trim($texto), 'UTF-8');
        $limpio = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $limpio) ?? $limpio;
        $tokens = preg_split('/\s+/u', $limpio, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return $tokens;
    }

    /**
     * Traducción heurística basada en reemplazo de glosario y preservación léxica.
     */
    private function traducirHeuristico(string $texto, string $idiomaDestino): string
    {
        $resultado = $texto;

        if ($idiomaDestino === 'es') {
            // De inglés a español
            foreach (self::GLOSARIO_TERMINOLOGICO as $en => $es) {
                $resultado = preg_replace('/\b' . preg_quote($en, '/') . '\b/iu', $es, $resultado) ?? $resultado;
            }
        } else {
            // De español a inglés
            foreach (self::GLOSARIO_TERMINOLOGICO as $en => $es) {
                $resultado = preg_replace('/\b' . preg_quote($es, '/') . '\b/iu', $en, $resultado) ?? $resultado;
            }
        }

        return $resultado;
    }

    /**
     * Registra auditoría técnica en la tabla logs_comunicador_api.
     */
    private function registrarLog(
        string $tipoOperacion,
        int $codigoRespuesta,
        ?string $detalleError
    ): void {
        try {
            LogComunicadorApi::create([
                'id_proceso'       => null,
                'servicio'         => 'Interlingue',
                'tipo_operacion'   => $tipoOperacion,
                'codigo_respuesta' => $codigoRespuesta,
                'detalle_error'    => $detalleError ? substr($detalleError, 0, 1000) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ServicioInterlingue] No se pudo guardar log en logs_comunicador_api: ' . $e->getMessage());
        }
    }
}
